<?php

namespace App\Services\Form;

use App\Enums\EventFormVisibility;
use App\Enums\FormAccessStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\FormPurpose;
use App\Enums\RegistrationRole;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Centralised access guard for the form fill / submit flow.
 *
 * Checks are evaluated in priority order so the most actionable reason is
 * always returned first. Admins (users with the `events.list` permission) are
 * exempt from visibility and registration-window checks but are still subject
 * to the duplicate-submission check.
 */
final class FormAccessGuard
{
    /**
     * Evaluate whether the given user may fill or submit the form.
     *
     * Order of checks:
     *  1. Visibility (`form.visible_for`)
     *  2. Form closure (`form.closed_at`)
     *  3. Registration forms: registration window, quota, other registration form chosen
     *  4. Other forms: prerequisite (accepted submission on required form)
     *  5. Duplicate / pending invitation / terminal invitation for this form
     */
    public static function check(Form $form, Event $event, User $user): FormAccessStatus
    {
        $isAdmin = $user->can('events.list');

        if (! $isAdmin && ! self::isVisible($form, $user)) {
            return FormAccessStatus::NotVisible;
        }

        if (self::isFormClosed($form)) {
            return FormAccessStatus::FormClosed;
        }

        if ($form->isRegistrationForm()) {
            if (! $isAdmin && self::isRegistrationWindowClosed($event)) {
                return FormAccessStatus::RegistrationNotOpen;
            }

            if (! $isAdmin && self::isQuotaFull($event)) {
                return FormAccessStatus::QuotaFull;
            }

            if (! $isAdmin && self::hasSubmissionOnOtherRegistrationFormInEvent($form, $event, $user)) {
                return FormAccessStatus::EventFormAlreadyChosen;
            }
        } else {
            if (! $isAdmin && ! self::prerequisiteAccepted($form, $event, $user)) {
                return FormAccessStatus::PrerequisiteNotMet;
            }
        }

        return self::duplicateOrInvitationStatus($form, $user)
            ?? FormAccessStatus::Allowed;
    }

    /**
     * When access is blocked for an existing row, the fill page may link here.
     */
    public static function pendingTeamInvitationUrl(Form $form, User $user): ?string
    {
        $existing = FormAnswer::query()
            ->where('form_id', $form->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing === null) {
            return null;
        }

        if ($existing->registration_role !== RegistrationRole::Member) {
            return null;
        }

        if (! $existing->isMemberPendingInvitation()) {
            return null;
        }

        $token = $existing->invitation_token;
        if ($token === null || $token === '') {
            return null;
        }

        return route('dashboard.user.team-invitations.show', ['token' => $token], absolute: false);
    }

    // -------------------------------------------------------------------------

    private static function isVisible(Form $form, User $user): bool
    {
        $visibleFor = $form->visible_for;

        if ($visibleFor === null || $visibleFor->isEmpty()) {
            return true;
        }

        $values = $visibleFor->map(fn (EventFormVisibility $v) => $v->value)->values()->all();
        $isOrganizer = $user->can('events.list');

        $hasPublic      = in_array(EventFormVisibility::Public->value, $values, true);
        $hasParticipant = in_array(EventFormVisibility::Participant->value, $values, true);
        $hasAdmin       = in_array(EventFormVisibility::Admin->value, $values, true);

        if ($hasPublic) {
            return true;
        }

        // Participant-only (no Admin flag): any logged-in dashboard user.
        // If Admin is also selected, "participant" alone must not open access — organizers use the Admin rule below.
        if ($hasParticipant && ! $hasAdmin) {
            return true;
        }

        return $hasAdmin && $isOrganizer;
    }

    private static function isFormClosed(Form $form): bool
    {
        return $form->closed_at !== null && Carbon::now()->isAfter($form->closed_at);
    }

    private static function isRegistrationWindowClosed(Event $event): bool
    {
        $now = Carbon::now();

        if ($event->registration_start !== null && $now->isBefore($event->registration_start)) {
            return true;
        }

        if ($event->registration_end !== null && $now->isAfter($event->registration_end)) {
            return true;
        }

        return false;
    }

    private static function isQuotaFull(Event $event): bool
    {
        return $event->quota !== null
            && $event->quota > 0
            && $event->registered_count >= $event->quota;
    }

    /**
     * One registration form per user per event (other-purpose forms do not count).
     */
    private static function hasSubmissionOnOtherRegistrationFormInEvent(Form $form, Event $event, User $user): bool
    {
        return FormAnswer::query()
            ->where('user_id', $user->id)
            ->where('form_id', '!=', $form->id)
            ->whereHas('form', function ($q) use ($event): void {
                $q->where('event_id', $event->id)
                    ->where(function ($purposeQuery): void {
                        $purposeQuery
                            ->whereNull('metadata')
                            ->orWhereNull('metadata->purpose')
                            ->orWhere('metadata->purpose', FormPurpose::Registration->value);
                    });
            })
            ->excludeRejectedSubmissions()
            ->exists();
    }

    /**
     * Non-registration forms may require an accepted submission on another form.
     */
    private static function prerequisiteAccepted(Form $form, Event $event, User $user): bool
    {
        $requiresFormId = $form->requiresFormId();
        if ($requiresFormId === null) {
            return true;
        }

        $requiredBelongsToEvent = Form::query()
            ->where('id', $requiresFormId)
            ->where('event_id', $event->id)
            ->exists();

        if (! $requiredBelongsToEvent) {
            return false;
        }

        return FormAnswer::query()
            ->where('form_id', $requiresFormId)
            ->where('user_id', $user->id)
            ->where('review_status', FormAnswerReviewStatus::Accepted)
            ->exists();
    }

    private static function duplicateOrInvitationStatus(Form $form, User $user): ?FormAccessStatus
    {
        $existing = FormAnswer::query()
            ->where('form_id', $form->id)
            ->where('user_id', $user->id)
            ->excludeRejectedSubmissions()
            ->first();

        if ($existing === null) {
            return null;
        }

        if ($existing->registration_role === RegistrationRole::Member) {
            if ($existing->isMemberPendingInvitation()) {
                return FormAccessStatus::PendingTeamConfirmation;
            }

            if ($existing->isInvitationTerminal()) {
                return FormAccessStatus::InvitationClosed;
            }
        }

        return FormAccessStatus::AlreadySubmitted;
    }
}

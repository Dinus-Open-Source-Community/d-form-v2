<?php

namespace App\Services\Form;

use App\Enums\EventFormVisibility;
use App\Enums\FormAccessStatus;
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
     *  3. Registration window (`event.registration_start/end`)
     *  4. Quota (`event.quota` vs `event.registered_count`)
     *  5. Another form in the same event already has a submission from this user
     *  6. Duplicate / pending invitation / terminal invitation for this form
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

        if (! $isAdmin && self::isRegistrationWindowClosed($event)) {
            return FormAccessStatus::RegistrationNotOpen;
        }

        if (! $isAdmin && self::isQuotaFull($event)) {
            return FormAccessStatus::QuotaFull;
        }

        if (! $isAdmin && self::hasSubmissionOnOtherFormInEvent($form, $event, $user)) {
            return FormAccessStatus::EventFormAlreadyChosen;
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

        $values = $visibleFor->map(fn (EventFormVisibility $v) => $v->value)->all();

        if (in_array(EventFormVisibility::Public->value, $values, true)) {
            return true;
        }

        if (in_array(EventFormVisibility::Participant->value, $values, true)) {
            return true;
        }

        return false;
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

    private static function hasSubmissionOnOtherFormInEvent(Form $form, Event $event, User $user): bool
    {
        return FormAnswer::query()
            ->where('user_id', $user->id)
            ->where('form_id', '!=', $form->id)
            ->whereHas('form', function ($q) use ($event): void {
                $q->where('event_id', $event->id);
            })
            ->exists();
    }

    private static function duplicateOrInvitationStatus(Form $form, User $user): ?FormAccessStatus
    {
        $existing = FormAnswer::query()
            ->where('form_id', $form->id)
            ->where('user_id', $user->id)
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

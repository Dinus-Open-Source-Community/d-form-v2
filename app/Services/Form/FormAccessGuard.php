<?php

namespace App\Services\Form;

use App\Enums\EventFormVisibility;
use App\Enums\FormAccessStatus;
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
     *  5. Duplicate submission
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

        if (self::hasAlreadySubmitted($form, $user)) {
            return FormAccessStatus::AlreadySubmitted;
        }

        return FormAccessStatus::Allowed;
    }

    // -------------------------------------------------------------------------

    /**
     * Returns true if the authenticated user is allowed to see the form based
     * on `visible_for`. Admins bypass this check before calling this method.
     *
     * Rules:
     *  - Empty collection → treated as public (open to all authenticated users).
     *  - Contains `public` → any authenticated user.
     *  - Contains `participant` → any authenticated user (full participant check
     *    is deferred to a future milestone when approval flow is implemented).
     *  - Contains `admin` only → blocked for non-admins.
     */
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

        // Only 'admin' remains → non-admin users are blocked.
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

    private static function hasAlreadySubmitted(Form $form, User $user): bool
    {
        return FormAnswer::query()
            ->where('form_id', $form->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}

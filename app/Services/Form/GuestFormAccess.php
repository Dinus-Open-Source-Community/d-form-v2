<?php

namespace App\Services\Form;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;

/** Fail-closed eligibility policy for anonymous access to event forms. */
final class GuestFormAccess
{
    public static function isEligible(Event $event, Form $form): bool
    {
        if ($form->event_id !== $event->id || $event->trashed() || $event->status !== EventStatus::Published) {
            return false;
        }

        $metadata = is_array($form->metadata) ? $form->metadata : [];
        $purpose = $form->purpose()->value;

        return in_array($purpose, ['answer_collection', 'feedback', 'other'], true)
            && ($metadata['allow_guest_submissions'] ?? false) === true
            && ! $form->isDraft()
            && ($form->closed_at === null || $form->closed_at->isFuture())
            && ! $form->isRegistrationForm();
    }

    public static function sessionKey(Form $form): string
    {
        return 'guest_form_access.'.$form->id;
    }

    public static function assertEligible(Event $event, Form $form): void
    {
        abort_unless(self::isEligible($event, $form), 404);
    }
}

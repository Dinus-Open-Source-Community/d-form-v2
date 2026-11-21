<?php

namespace App\Services\Form;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormSubmissionMode;
use App\Models\Event;
use App\Models\Form;
use App\Services\Registration\FormSubmissionRules;

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
            && $form->closed_at === null
            && ($form->visible_for?->contains(EventFormVisibility::Public) ?? false)
            && $form->requiresFormId() === null
            && FormSubmissionRules::modeFor($form) === FormSubmissionMode::Other;
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

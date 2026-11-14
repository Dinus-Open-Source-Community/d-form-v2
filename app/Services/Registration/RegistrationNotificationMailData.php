<?php

namespace App\Services\Registration;

use App\Models\FormAnswer;
use App\Models\User;
use App\Support\RegistrationPortalLinks;

final class RegistrationNotificationMailData
{
    public function __construct(
        private BundleGuestDisplayNameResolver $displayNameResolver,
    ) {
    }

    /**
     *
     * @return array<string, mixed>
     *
     */
    public function shared(FormAnswer $submission, ?User $greetingUser = null): array
    {
        $submission->loadMissing(['form.event', 'user']);

        $greeting = $greetingUser ?? $submission->user;
        $recipientName = $greeting !== null && $greeting->name !== ''
            ? $greeting->name
            : $this->displayNameResolver->resolve($submission);

        $isGuestRecipient = $greeting === null && $submission->user_id === null;

        $event = $submission->form->event;

        if ($isGuestRecipient) {
            $registrationActionUrl = RegistrationPortalLinks::publicEventUrl($event);
            $registrationActionLabel = __('View event details');
        } else {
            $registrationActionUrl = RegistrationPortalLinks::registrationDetailsUrl($event);
            $registrationActionLabel = __('View registration details');
        }

        return [
            'submission' => $submission,
            'event' => $event,
            'form' => $submission->form,
            'recipientName' => $recipientName,
            'isGuestRecipient' => $isGuestRecipient,
            'registrationActionUrl' => $registrationActionUrl,
            'registrationActionLabel' => $registrationActionLabel,
            'showRegistrationAction' => true,
            'showSubmissionId' => ! $isGuestRecipient,
            'teammateDisplayName' => $this->displayNameResolver->resolve($submission),
        ];
    }
}

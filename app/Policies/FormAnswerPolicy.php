<?php

namespace App\Policies;

use App\Enums\RegistrationRole;
use App\Models\FormAnswer;
use App\Models\User;

class FormAnswerPolicy
{
    public function review(User $user, FormAnswer $formAnswer): bool
    {
        return $user->can('events.view');
    }

    /** Allow access to a submission file for its owner, an organizer who can view the event, or the team leader of the same group. */
    public function viewFile(User $user, FormAnswer $formAnswer): bool
    {
        if ((string) $formAnswer->user_id === (string) $user->id) {
            return true;
        }

        $formAnswer->loadMissing('form.event');
        $event = $formAnswer->form?->event;

        if ($event !== null && $user->can('view', $event)) {
            return true;
        }

        if ($formAnswer->registration_role !== RegistrationRole::Member) {
            return false;
        }

        $groupToken = $formAnswer->group_token;

        if (! is_string($groupToken) || $groupToken === '') {
            return false;
        }

        return FormAnswer::query()
            ->where('group_token', $groupToken)
            ->where('registration_role', RegistrationRole::Leader)
            ->where('user_id', (string) $user->id)
            ->exists();
    }
}

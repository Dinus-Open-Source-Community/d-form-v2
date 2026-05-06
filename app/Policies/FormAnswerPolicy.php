<?php

namespace App\Policies;

use App\Models\FormAnswer;
use App\Models\User;

class FormAnswerPolicy
{
    public function review(User $user, FormAnswer $formAnswer): bool
    {
        return $user->can('events.view');
    }
}


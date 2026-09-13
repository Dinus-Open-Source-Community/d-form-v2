<?php

namespace App\Policies\Recruitment;

use App\Models\Recruitment\RecruitmentEmailTemplate;
use App\Models\User;

class RecruitmentEmailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->can('recruitment.templates.list');
    }

    public function view(User $user, RecruitmentEmailTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, RecruitmentEmailTemplate $template): bool
    {
        return $user->hasRole('super-admin') || $user->can('recruitment.templates.edit');
    }
}

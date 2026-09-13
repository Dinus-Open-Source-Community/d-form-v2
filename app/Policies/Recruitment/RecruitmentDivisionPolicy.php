<?php

namespace App\Policies\Recruitment;

use App\Models\Recruitment\RecruitmentDivision;
use App\Models\User;

class RecruitmentDivisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.divisions.list');
    }

    public function view(User $user, RecruitmentDivision $division): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.divisions.view');
    }

    public function update(User $user, RecruitmentDivision $division): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.divisions.edit');
    }

    public function assignInterviewer(User $user): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.interviewers.assign');
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super-admin');
    }
}

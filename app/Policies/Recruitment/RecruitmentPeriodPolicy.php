<?php

namespace App\Policies\Recruitment;

use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;

class RecruitmentPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.periods.list');
    }

    public function view(User $user, RecruitmentPeriod $period): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.periods.view');
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.periods.create');
    }

    public function update(User $user, RecruitmentPeriod $period): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.periods.edit');
    }

    public function delete(User $user, RecruitmentPeriod $period): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.periods.delete');
    }

    public function open(User $user, RecruitmentPeriod $period): bool
    {
        return $this->update($user, $period);
    }

    public function close(User $user, RecruitmentPeriod $period): bool
    {
        return $this->update($user, $period);
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super-admin');
    }
}

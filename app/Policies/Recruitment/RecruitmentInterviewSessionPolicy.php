<?php

namespace App\Policies\Recruitment;

use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\User;

class RecruitmentInterviewSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.interviews.schedule');
    }

    public function view(User $user, RecruitmentInterviewSession $session): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function viewQueue(User $user, RecruitmentInterviewSession $session): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.queue.view');
    }

    public function manageQueue(User $user, RecruitmentInterviewSession $session): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.queue.manage');
    }

    public function scanAttendance(User $user, ?RecruitmentInterviewSession $session = null): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.attendance.scan');
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super-admin');
    }
}

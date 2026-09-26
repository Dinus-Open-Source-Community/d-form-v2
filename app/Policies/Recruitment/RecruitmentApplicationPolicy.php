<?php

namespace App\Policies\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\User;

class RecruitmentApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.applications.list');
    }

    public function view(User $user, RecruitmentApplication $application): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->can('recruitment.applications.list')
            || ($user->can('recruitment.applications.view') && $user->can('recruitment.screening.decide'));
    }

    public function viewAssignedInterview(User $user, RecruitmentApplication $application): bool
    {
        if ($this->view($user, $application)) {
            return true;
        }

        return $this->isAssignedInterviewer($user, $application)
            && $user->can('recruitment.evaluations.view');
    }

    public function downloadDocument(User $user, RecruitmentApplication $application): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($user->can('recruitment.applications.view') && $user->can('recruitment.screening.decide')) {
            return true;
        }

        return $this->isAssignedInterviewer($user, $application)
            && $user->can('recruitment.evaluations.view');
    }

    public function evaluate(User $user, RecruitmentApplication $application): bool
    {
        if (! $user->can('recruitment.evaluations.submit')) {
            return false;
        }

        $application->loadMissing('evaluation');

        if ($application->evaluation?->isLocked() && ! $this->canStaffManage($user)) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($this->canStaffManage($user)) {
            return true;
        }

        return $this->isAssignedInterviewer($user, $application);
    }

    public function overrideEvaluation(User $user, RecruitmentApplication $application): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->canStaffManage($user);
    }

    public function screen(User $user, RecruitmentApplication $application): bool
    {
        return $this->isSuperAdmin($user) || $user->can('recruitment.screening.decide');
    }

    public function resendTrackingInformation(User $user, RecruitmentApplication $application): bool
    {
        if (! $this->view($user, $application)) {
            return false;
        }

        if ($application->cancelled_at !== null) {
            return false;
        }

        if (blank($application->personal_email)) {
            return false;
        }

        return $this->isSuperAdmin($user) || $this->canStaffManage($user);
    }

    public function decideFinal(User $user, RecruitmentApplication $application): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (! $user->can('recruitment.final.decide')) {
            return false;
        }

        if ($application->cancelled_at !== null) {
            return false;
        }

        return $application->stage === \App\Enums\Recruitment\ApplicationStage::FinalReview
            && $application->result === \App\Enums\Recruitment\ApplicationResult::Pending;
    }

    private function isAssignedInterviewer(User $user, RecruitmentApplication $application): bool
    {
        return RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->where('interviewer_id', $user->id)
            ->exists();
    }

    private function canStaffManage(User $user): bool
    {
        return $user->can('recruitment.screening.decide')
            && $user->can('recruitment.applications.view');
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super-admin');
    }
}

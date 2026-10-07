<?php

namespace App\Policies\Recruitment;

use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    public function viewAssignedInterview(User $user, RecruitmentApplication $application): Response|bool
    {
        if ($this->view($user, $application)) {
            return true;
        }

        if (! $this->isAssignedInterviewer($user, $application)
            || ! $user->can('recruitment.evaluations.view')) {
            return false;
        }

        // Interviewer murni hanya boleh membuka applicant yang sudah regis ulang.
        if (! $this->hasCheckedIn($application)) {
            return Response::deny('Belum regis ulang (scan QR).');
        }

        return true;
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

    public function bookInterview(User $user, RecruitmentApplication $application): bool
    {
        if (! $user->can('recruitment.evaluations.submit')) {
            return false;
        }

        $application->loadMissing('interview', 'evaluation', 'attendance');

        if ($application->attendance === null || $application->evaluation !== null) {
            return false;
        }

        $interview = $application->interview;

        if ($interview === null
            || $interview->status !== InterviewStatus::Waiting
            || $interview->interviewer_id !== null) {
            return false;
        }

        return $this->interviewerAssignedToApplicationDivision($user, $application);
    }

    public function releaseInterview(User $user, RecruitmentApplication $application): bool
    {
        if (! $user->can('recruitment.evaluations.submit')) {
            return false;
        }

        $application->loadMissing('interview', 'evaluation');

        if ($application->evaluation !== null) {
            return false;
        }

        $interview = $application->interview;

        return $interview !== null
            && $interview->status === InterviewStatus::InProgress
            && $interview->interviewer_id === $user->id;
    }

    public function evaluate(User $user, RecruitmentApplication $application): Response|bool
    {
        if (! $user->can('recruitment.evaluations.submit')) {
            return false;
        }

        $application->loadMissing('evaluation');

        // Nilai hanya boleh di-submit bila applicant sudah regis ulang (kecuali super-admin).
        if (! $this->hasCheckedIn($application) && ! $this->isSuperAdmin($user)) {
            return Response::deny('Belum regis ulang (scan QR).');
        }

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

    private function interviewerAssignedToApplicationDivision(User $user, RecruitmentApplication $application): bool
    {
        $application->loadMissing('interview.session');
        $divisionId = $application->interview?->session?->recruitment_division_id
            ?? $application->primary_division_id;

        return RecruitmentInterviewerDivision::query()
            ->where('user_id', $user->id)
            ->where('recruitment_division_id', $divisionId)
            ->exists();
    }

    /**
     * Regist ulang sudah dilakukan bila baris attendance tersedia.
     */
    private function hasCheckedIn(RecruitmentApplication $application): bool
    {
        $application->loadMissing('attendance');

        return $application->attendance !== null;
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

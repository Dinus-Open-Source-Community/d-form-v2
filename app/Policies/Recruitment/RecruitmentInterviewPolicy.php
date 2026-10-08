<?php

namespace App\Policies\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RecruitmentInterviewPolicy
{
    public function view(User $user, RecruitmentInterview $interview): Response|bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $interview->loadMissing('application');

        if ($interview->application !== null && $this->broadView($user)) {
            return true;
        }

        if ($interview->interviewer_id !== $user->id
            || ! $user->can('recruitment.evaluations.view')) {
            return false;
        }

        // Interviewer murni hanya boleh membuka interview yang applicantnya sudah regis ulang.
        if ($interview->application === null || ! $this->hasCheckedIn($interview->application)) {
            return Response::deny('Belum regis ulang (scan QR).');
        }

        return true;
    }

    public function evaluate(User $user, RecruitmentInterview $interview): Response|bool
    {
        if (! $user->can('recruitment.evaluations.submit')) {
            return false;
        }

        $interview->loadMissing(['application', 'evaluation']);

        if ($interview->application === null) {
            return false;
        }

        // Nilai hanya boleh di-submit bila applicant sudah regis ulang (kecuali super-admin).
        if (! $this->hasCheckedIn($interview->application) && ! $this->isSuperAdmin($user)) {
            return Response::deny('Belum regis ulang (scan QR).');
        }

        $evaluation = $interview->evaluation;

        // Budget habis → terkunci untuk semua pihak, termasuk staff.
        if ($evaluation !== null && $evaluation->save_count >= RecruitmentEvaluation::MAX_SAVES) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($this->canStaffManage($user)) {
            return true;
        }

        return $interview->interviewer_id === $user->id;
    }

    public function override(User $user, RecruitmentInterview $interview): bool
    {
        $interview->loadMissing('evaluation');

        // Budget habis → override ditolak untuk semua pihak.
        if (($interview->evaluation?->save_count ?? 0) >= RecruitmentEvaluation::MAX_SAVES) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->canStaffManage($user);
    }

    private function broadView(User $user): bool
    {
        return $user->can('recruitment.applications.list')
            || ($user->can('recruitment.applications.view') && $user->can('recruitment.screening.decide'));
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

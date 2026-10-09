<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;

final class InterviewAutoEnrollService
{
    public function __construct(
        private readonly InterviewLifecycleService $lifecycle,
    ) {
    }

    /**
     * Sesi aktif terdekat milik divisi primer applicant pada periode yang sama.
     */
    public function resolveNearestSession(RecruitmentApplication $application): ?RecruitmentInterviewSession
    {
        if ($application->primary_division_id === null) {
            return null;
        }

        return RecruitmentInterviewSession::query()
            ->where('recruitment_period_id', $application->recruitment_period_id)
            ->where('recruitment_division_id', $application->primary_division_id)
            ->where('is_active', true)
            ->whereDate('session_date', '>=', today())
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * Daftarkan applicant ke sesi primer terdekat. Idempoten: kembalikan
     * primary interview yang sudah ada bila tersedia; null bila tak ada sesi.
     */
    public function enroll(RecruitmentApplication $application): ?RecruitmentInterview
    {
        $application->loadMissing('primaryInterview');

        if ($application->primaryInterview !== null) {
            return $application->primaryInterview;
        }

        $session = $this->resolveNearestSession($application);

        if ($session === null) {
            return null;
        }

        return $this->lifecycle->createWaitingInterview($application, $session);
    }
}

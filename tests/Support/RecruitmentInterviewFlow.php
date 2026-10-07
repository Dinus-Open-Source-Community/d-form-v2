<?php

namespace Tests\Support;

use App\Enums\Recruitment\AttendanceMethod;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\User;
use App\Services\Recruitment\AttendanceService;

trait RecruitmentInterviewFlow
{
    protected function applicationReadyForInterview(
        RecruitmentInterviewSession $session,
        array $overrides = [],
    ): RecruitmentApplication {
        return RecruitmentApplication::factory()->create(array_merge([
            'recruitment_period_id' => $session->recruitment_period_id,
            'primary_division_id' => $session->recruitment_division_id,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ], $overrides));
    }

    protected function checkInApplicant(
        RecruitmentInterviewSession $session,
        RecruitmentApplication $application,
        ?User $operator = null,
    ): void {
        app(AttendanceService::class)->checkIn(
            $session,
            $application,
            AttendanceMethod::RegistrationNumber,
            $operator,
        );
    }
}

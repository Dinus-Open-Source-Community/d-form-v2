<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use Illuminate\Support\Carbon;

final class InterviewLifecycleService
{
    public function createWaitingInterview(
        RecruitmentApplication $application,
        RecruitmentInterviewSession $session,
    ): RecruitmentInterview {
        $scheduledAt = $this->buildScheduledAt($session);

        return RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $session->id,
            'interviewer_id' => null,
            'scheduled_at' => $scheduledAt,
            'location' => $session->location,
            'room' => $session->room,
            'status' => InterviewStatus::Waiting,
        ]);
    }

    public function syncOrCreateWaitingInterview(
        RecruitmentApplication $application,
        RecruitmentInterviewSession $session,
    ): RecruitmentInterview {
        $application->loadMissing('interview');
        $interview = $application->interview;

        if ($interview === null) {
            return $this->createWaitingInterview($application, $session);
        }

        $scheduledAt = $this->buildScheduledAt($session);

        $interview->update([
            'recruitment_interview_session_id' => $session->id,
            'interviewer_id' => null,
            'booked_at' => null,
            'scheduled_at' => $scheduledAt,
            'location' => $session->location,
            'room' => $session->room,
            'status' => InterviewStatus::Waiting,
        ]);

        return $interview->fresh();
    }

    public function markCompleted(RecruitmentInterview $interview): void
    {
        $interview->update(['status' => InterviewStatus::Completed]);

        $application = $interview->application;

        if ($application === null) {
            return;
        }

        $this->advanceApplicationToFinalReview($application);
    }

    public function advanceApplicationToFinalReview(RecruitmentApplication $application): void
    {
        if ($application->stage === ApplicationStage::Interview
            && $application->result === ApplicationResult::Pending) {
            $application->update(['stage' => ApplicationStage::FinalReview]);
        }
    }

    private function buildScheduledAt(RecruitmentInterviewSession $session): Carbon
    {
        $date = $session->session_date?->format('Y-m-d');
        $time = substr((string) $session->starts_at, 0, 8);

        return Carbon::parse($date.' '.$time, config('app.timezone'));
    }
}

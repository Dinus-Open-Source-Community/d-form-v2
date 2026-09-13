<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;

final class RecruitmentDashboardService
{
    public function __construct(
        private readonly RecruitmentReportService $reportService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(?User $user = null, ?string $periodId = null): array
    {
        if ($user !== null && $this->isInterviewerOnly($user)) {
            return $this->interviewerSummary($user, $periodId);
        }

        $periodQuery = RecruitmentPeriod::query()->orderByDesc('created_at');
        $activePeriod = $periodId !== null
            ? RecruitmentPeriod::query()->find($periodId)
            : $periodQuery->where('status', 'open')->first()
                ?? $periodQuery->first();

        if ($activePeriod === null) {
            return [
                'active_period' => null,
                'stats' => $this->emptyStats(),
                'funnel' => [],
                'interview_stats' => $this->emptyInterviewStats(),
                'feedback' => $this->emptyFeedbackStats(),
                'accepted_count' => 0,
            ];
        }

        $applications = RecruitmentApplication::query()
            ->where('recruitment_period_id', $activePeriod->id);

        $report = $this->reportService->build($activePeriod->id);

        return [
            'active_period' => app(RecruitmentPeriodService::class)->toInertiaArray($activePeriod),
            'stats' => [
                'total_applicants' => (clone $applications)->count(),
                'pending_screening' => (clone $applications)->where('stage', ApplicationStage::Submitted)->count(),
                'in_screening' => (clone $applications)->where('stage', ApplicationStage::Screening)->count(),
                'passed_screening' => (clone $applications)->whereIn('stage', [
                    ApplicationStage::Interview,
                    ApplicationStage::FinalReview,
                ])->count(),
                'rejected_applicants' => (clone $applications)->where('result', ApplicationResult::Rejected)->count(),
                'in_interview' => (clone $applications)->where('stage', ApplicationStage::Interview)->count(),
                'final_review' => (clone $applications)->where('stage', ApplicationStage::FinalReview)->count(),
                'completed' => (clone $applications)->where('stage', ApplicationStage::Completed)->count(),
            ],
            'funnel' => $report['funnel'],
            'interview_stats' => $report['interview_stats'],
            'feedback' => $report['feedback'],
            'accepted_count' => (clone $applications)
                ->where('result', ApplicationResult::Accepted)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function interviewerSummary(User $user, ?string $periodId): array
    {
        $periodQuery = RecruitmentPeriod::query()->orderByDesc('created_at');
        $activePeriod = $periodId !== null
            ? RecruitmentPeriod::query()->find($periodId)
            : $periodQuery->where('status', 'open')->first()
                ?? $periodQuery->first();

        if ($activePeriod === null) {
            return [
                'active_period' => null,
                'stats' => $this->emptyInterviewerStats(),
                'is_interviewer_view' => true,
            ];
        }

        $interviews = RecruitmentInterview::query()
            ->where('interviewer_id', $user->id)
            ->whereHas('application', fn ($q) => $q->where('recruitment_period_id', $activePeriod->id));

        return [
            'active_period' => app(RecruitmentPeriodService::class)->toInertiaArray($activePeriod),
            'stats' => [
                'assigned_total' => (clone $interviews)->count(),
                'scheduled' => (clone $interviews)->where('status', InterviewStatus::Scheduled)->count(),
                'completed' => (clone $interviews)->where('status', InterviewStatus::Completed)->count(),
                'pending_evaluation' => (clone $interviews)
                    ->whereHas('application', fn ($q) => $q->whereDoesntHave('evaluation'))
                    ->whereIn('status', [
                        InterviewStatus::Called,
                        InterviewStatus::Completed,
                        InterviewStatus::InProgress,
                    ])
                    ->count(),
            ],
            'is_interviewer_view' => true,
        ];
    }

    private function isInterviewerOnly(User $user): bool
    {
        if ($user->hasRole('super-admin') || $user->can('recruitment.applications.list')) {
            return false;
        }

        return $user->can('recruitment.evaluations.view');
    }

    /**
     * @return array<string, int>
     */
    private function emptyStats(): array
    {
        return [
            'total_applicants' => 0,
            'pending_screening' => 0,
            'in_screening' => 0,
            'passed_screening' => 0,
            'rejected_applicants' => 0,
            'in_interview' => 0,
            'final_review' => 0,
            'completed' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyInterviewerStats(): array
    {
        return [
            'assigned_total' => 0,
            'scheduled' => 0,
            'completed' => 0,
            'pending_evaluation' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyInterviewStats(): array
    {
        return [
            'scheduled' => 0,
            'called' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'no_show' => 0,
            'total' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFeedbackStats(): array
    {
        return [
            'count' => 0,
            'averages' => [],
        ];
    }
}

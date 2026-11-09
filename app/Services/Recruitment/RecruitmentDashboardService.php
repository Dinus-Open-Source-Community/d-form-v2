<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Enums\Recruitment\RecruitmentPeriodStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class RecruitmentDashboardService
{
    public function __construct(
        private readonly RecruitmentReportService $reportService,
        private readonly RecruitmentApplicationService $applicationService,
        private readonly InterviewSessionService $sessionService,
        private readonly RecruitmentPeriodService $periodService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $periodFilters
     * @return array<string, mixed>
     */
    public function summary(?User $user = null, ?string $periodId = null, array $periodFilters = [], int $periodPage = 1, int $periodPerPage = 10): array
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
            return array_merge([
                'active_period' => null,
                'stats' => $this->emptyStats(),
                'funnel' => [],
                'interview_stats' => $this->emptyInterviewStats(),
                'feedback' => $this->emptyFeedbackStats(),
                'accepted_count' => 0,
                'action_queues' => [],
                'today_sessions' => [],
            ], $this->periodsPayload($periodFilters, $periodPage, $periodPerPage, $user));
        }

        $stats = $this->applicationStats($activePeriod->id);

        $report = $this->reportService->build($activePeriod->id);
        $queueCounts = $this->applicationService->queueCounts($activePeriod->id);

        return array_merge([
            'active_period' => app(RecruitmentPeriodService::class)->toInertiaArray($activePeriod),
            'stats' => $stats['stats'],
            'funnel' => $report['funnel'],
            'interview_stats' => $report['interview_stats'],
            'feedback' => $report['feedback'],
            'accepted_count' => $stats['accepted_count'],
            'action_queues' => $this->buildActionQueues($queueCounts),
            'today_sessions' => $this->sessionService->todaySessions($activePeriod->id),
        ], $this->periodsPayload($periodFilters, $periodPage, $periodPerPage, $user));
    }

    /**
     * Delapan angka statistik + accepted_count dalam satu query agregat
     * kondisional (sebelumnya 9 COUNT terpisah pada periode yang sama).
     *
     * @return array{stats: array<string, int>, accepted_count: int}
     */
    private function applicationStats(string $periodId): array
    {
        /** @var array<string, int|string|null> $row SUM bisa kembali string di driver tertentu. */
        $row = (array) RecruitmentApplication::query()
            ->where('recruitment_period_id', $periodId)
            ->toBase()
            ->selectRaw(
                'COUNT(*) as total_applicants,'
                .' SUM(CASE WHEN stage = ? THEN 1 ELSE 0 END) as pending_screening,'
                .' SUM(CASE WHEN stage = ? THEN 1 ELSE 0 END) as in_screening,'
                .' SUM(CASE WHEN stage IN (?, ?) THEN 1 ELSE 0 END) as passed_screening,'
                .' SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) as rejected_applicants,'
                .' SUM(CASE WHEN stage = ? THEN 1 ELSE 0 END) as in_interview,'
                .' SUM(CASE WHEN stage = ? THEN 1 ELSE 0 END) as final_review,'
                .' SUM(CASE WHEN stage = ? THEN 1 ELSE 0 END) as completed,'
                .' SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) as accepted_count',
                [
                    ApplicationStage::Submitted->value,
                    ApplicationStage::Screening->value,
                    ApplicationStage::Interview->value,
                    ApplicationStage::FinalReview->value,
                    ApplicationResult::Rejected->value,
                    ApplicationStage::Interview->value,
                    ApplicationStage::FinalReview->value,
                    ApplicationStage::Completed->value,
                    ApplicationResult::Accepted->value,
                ],
            )
            ->first();

        return [
            'stats' => [
                'total_applicants' => (int) ($row['total_applicants'] ?? 0),
                'pending_screening' => (int) ($row['pending_screening'] ?? 0),
                'in_screening' => (int) ($row['in_screening'] ?? 0),
                'passed_screening' => (int) ($row['passed_screening'] ?? 0),
                'rejected_applicants' => (int) ($row['rejected_applicants'] ?? 0),
                'in_interview' => (int) ($row['in_interview'] ?? 0),
                'final_review' => (int) ($row['final_review'] ?? 0),
                'completed' => (int) ($row['completed'] ?? 0),
            ],
            'accepted_count' => (int) ($row['accepted_count'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{periods: LengthAwarePaginator, query: array<string, mixed>, statusOptions: list<array{value: string, label: string}>}
     */
    private function periodsPayload(array $filters = [], int $page = 1, int $perPage = 20, ?User $user = null): array
    {
        $page = max(1, $page);
        // Aturan global: 20 data per halaman; pager hanya bila total > 20.
        $perPage = 20;

        $paginator = $this->periodService->paginate($filters, $page, $perPage, $user);
        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (RecruitmentPeriod $period) => $this->periodService->toInertiaArray($period, $user)
            )
        );

        return [
            'periods' => $paginator,
            'query' => $filters,
            'statusOptions' => collect(RecruitmentPeriodStatus::cases())
                ->map(fn (RecruitmentPeriodStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                ->values()
                ->all(),
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
                'waiting' => (clone $interviews)->where('status', InterviewStatus::Waiting)->count(),
                'in_progress' => (clone $interviews)->where('status', InterviewStatus::InProgress)->count(),
                'completed' => (clone $interviews)->where('status', InterviewStatus::Completed)->count(),
                'pending_evaluation' => (clone $interviews)
                    ->whereHas('application', fn ($q) => $q->whereDoesntHave('evaluation'))
                    ->where('status', InterviewStatus::InProgress)
                    ->count(),
            ],
            'is_interviewer_view' => true,
        ];
    }

    public function isInterviewerOnly(User $user): bool
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
            'waiting' => 0,
            'in_progress' => 0,
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
            'waiting' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
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

    /**
     * @param  array<string, int>  $counts
     * @return list<array<string, mixed>>
     */
    private function buildActionQueues(array $counts): array
    {
        $correctionsPending = $counts['corrections_pending'] ?? 0;

        $queues = [
            [
                'key' => 'screening',
                'label' => 'Perlu screening',
                'description' => 'Applicant baru menunggu tinjauan dokumen',
                'count' => $counts['screening'] ?? 0,
            ],
            [
                'key' => 'revision',
                'label' => 'Revisi & koreksi',
                'description' => $correctionsPending > 0
                    ? "{$correctionsPending} permintaan koreksi menunggu review"
                    : 'Applicant diminta revisi pendaftaran',
                'count' => max($counts['revision'] ?? 0, $correctionsPending),
            ],
            [
                'key' => 'final',
                'label' => 'Keputusan final',
                'description' => 'Siap ditinjau untuk Terima / Tolak',
                'count' => $counts['final'] ?? 0,
            ],
        ];

        return $queues;
    }
}

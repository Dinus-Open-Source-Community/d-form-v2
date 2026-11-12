<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentFeedback;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentPeriod;

final class RecruitmentReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(?string $periodId = null): array
    {
        $period = $this->findReportPeriod($periodId);

        if ($period === null) {
            return [
                'period' => null,
                'funnel' => [],
                'by_division' => [],
                'by_semester' => [],
                'interview_stats' => $this->emptyInterviewStats(),
                'feedback' => $this->emptyFeedbackStats(),
            ];
        }

        return [
            'period' => app(RecruitmentPeriodService::class)->toInertiaArray($period),
            'funnel' => $this->buildFunnel($period->id),
            'by_division' => $this->buildByDivision($period->id),
            'by_semester' => $this->buildBySemester($period->id),
            'interview_stats' => $this->buildInterviewStats($period->id),
            'feedback' => $this->buildFeedbackStats($period->id),
        ];
    }

    /**
     * @return list<array{stage: string, label: string, count: int}>
     */
    public function buildFunnel(string $periodId): array
    {
        $counts = RecruitmentApplication::query()
            ->where('recruitment_period_id', $periodId)
            ->selectRaw('stage, COUNT(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $resultCounts = RecruitmentApplication::query()
            ->where('recruitment_period_id', $periodId)
            ->where('stage', ApplicationStage::Completed)
            ->selectRaw('result, COUNT(*) as total')
            ->groupBy('result')
            ->pluck('total', 'result');

        $funnel = [];

        foreach (ApplicationStage::timelineOrder() as $stage) {
            $funnel[] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'count' => (int) ($counts[$stage->value] ?? 0),
            ];
        }

        $funnel[] = [
            'stage' => 'accepted',
            'label' => 'Diterima',
            'count' => (int) ($resultCounts[ApplicationResult::Accepted->value] ?? 0),
        ];

        $funnel[] = [
            'stage' => 'rejected',
            'label' => 'Tidak diterima',
            'count' => (int) ($resultCounts[ApplicationResult::Rejected->value] ?? 0),
        ];

        return $funnel;
    }

    /**
     * Resolve the period that a report section would use without building the
     * full report. Explicit id wins; otherwise the newest open period, falling
     * back to the newest period.
     */
    public function resolvePeriodModel(?string $periodId = null): ?RecruitmentPeriod
    {
        return $this->findReportPeriod($periodId);
    }

    private function findReportPeriod(?string $periodId): ?RecruitmentPeriod
    {
        if ($periodId !== null) {
            return RecruitmentPeriod::query()->find($periodId);
        }

        return RecruitmentPeriod::query()
            ->orderByDesc('created_at')
            ->where('status', 'open')
            ->first()
            ?? RecruitmentPeriod::query()->orderByDesc('created_at')->first();
    }

    /**
     * @return list<array{division: string, count: int}>
     */
    private function buildByDivision(string $periodId): array
    {
        $rows = RecruitmentApplication::query()
            ->where('recruitment_period_id', $periodId)
            ->join('recruitment_divisions', 'recruitment_applications.primary_division_id', '=', 'recruitment_divisions.id')
            ->selectRaw('recruitment_divisions.name as division_name, COUNT(*) as total')
            ->groupBy('recruitment_divisions.name', 'recruitment_divisions.sort_order')
            ->orderBy('recruitment_divisions.sort_order')
            ->get();

        return $rows->map(fn ($row): array => [
            'division' => (string) $row->division_name,
            'count' => (int) $row->total,
        ])->all();
    }

    /**
     * @return list<array{semester: int, count: int}>
     */
    private function buildBySemester(string $periodId): array
    {
        return RecruitmentApplication::query()
            ->where('recruitment_period_id', $periodId)
            ->selectRaw('semester, COUNT(*) as total')
            ->groupBy('semester')
            ->orderBy('semester')
            ->get()
            ->map(fn ($row): array => [
                'semester' => (int) $row->semester,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Counts interviews of a period with one aggregate query so no application
     * ID list is materialised in PHP. `total` sums every grouped status, which
     * preserves the previous count(*) semantics for unknown status values.
     *
     * @return array<string, int>
     */
    private function buildInterviewStats(string $periodId): array
    {
        $statusTotals = RecruitmentInterview::query()
            ->whereIn(
                'recruitment_application_id',
                RecruitmentApplication::query()
                    ->select('id')
                    ->where('recruitment_period_id', $periodId)
            )
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = $this->emptyInterviewStats();

        foreach (InterviewStatus::cases() as $status) {
            $stats[$status->value] = (int) ($statusTotals[$status->value] ?? 0);
        }

        $stats['total'] = (int) $statusTotals->sum();

        return $stats;
    }

    /**
     * One aggregate query replaces the previous count + five separate AVG
     * queries. Averages are aliased because the model casts the rating
     * columns to integer and would truncate an AVG value.
     *
     * @return array<string, mixed>
     */
    private function buildFeedbackStats(string $periodId): array
    {
        $row = RecruitmentFeedback::query()
            ->where('recruitment_period_id', $periodId)
            ->selectRaw('COUNT(*) as feedback_count')
            ->selectRaw('AVG(rating_registration_ease) as avg_registration_ease')
            ->selectRaw('AVG(rating_info_clarity) as avg_info_clarity')
            ->selectRaw('AVG(rating_tracking_ease) as avg_tracking_ease')
            ->selectRaw('AVG(rating_interview_experience) as avg_interview_experience')
            ->selectRaw('AVG(rating_staff_service) as avg_staff_service')
            ->first();

        if ($row === null || (int) $row->feedback_count === 0) {
            return $this->emptyFeedbackStats();
        }

        return [
            'count' => (int) $row->feedback_count,
            'averages' => [
                'registration_ease' => round((float) $row->avg_registration_ease, 2),
                'info_clarity' => round((float) $row->avg_info_clarity, 2),
                'tracking_ease' => round((float) $row->avg_tracking_ease, 2),
                'interview_experience' => round((float) $row->avg_interview_experience, 2),
                'staff_service' => round((float) $row->avg_staff_service, 2),
            ],
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
            'averages' => [
                'registration_ease' => null,
                'info_clarity' => null,
                'tracking_ease' => null,
                'interview_experience' => null,
                'staff_service' => null,
            ],
        ];
    }
}

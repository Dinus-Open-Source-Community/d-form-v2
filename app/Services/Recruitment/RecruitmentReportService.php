<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentFeedback;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentPeriod;
use Illuminate\Support\Collection;

final class RecruitmentReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(?string $periodId = null): array
    {
        $period = $this->resolvePeriod($periodId);

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

        $applications = RecruitmentApplication::query()
            ->where('recruitment_period_id', $period->id);

        $applicationIds = (clone $applications)->pluck('id');

        return [
            'period' => app(RecruitmentPeriodService::class)->toInertiaArray($period),
            'funnel' => $this->buildFunnel($period->id),
            'by_division' => $this->buildByDivision($period->id),
            'by_semester' => $this->buildBySemester($period->id),
            'interview_stats' => $this->buildInterviewStats($applicationIds),
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

    private function resolvePeriod(?string $periodId): ?RecruitmentPeriod
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
     * @param  Collection<int, string>  $applicationIds
     * @return array<string, int>
     */
    private function buildInterviewStats(Collection $applicationIds): array
    {
        if ($applicationIds->isEmpty()) {
            return $this->emptyInterviewStats();
        }

        $interviews = RecruitmentInterview::query()
            ->whereIn('recruitment_application_id', $applicationIds);

        return [
            'scheduled' => (clone $interviews)->where('status', InterviewStatus::Scheduled)->count(),
            'called' => (clone $interviews)->where('status', InterviewStatus::Called)->count(),
            'completed' => (clone $interviews)->where('status', InterviewStatus::Completed)->count(),
            'cancelled' => (clone $interviews)->where('status', InterviewStatus::Cancelled)->count(),
            'no_show' => (clone $interviews)->where('status', InterviewStatus::NoShow)->count(),
            'total' => (clone $interviews)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFeedbackStats(string $periodId): array
    {
        $feedbacks = RecruitmentFeedback::query()->where('recruitment_period_id', $periodId);

        $count = (clone $feedbacks)->count();

        if ($count === 0) {
            return $this->emptyFeedbackStats();
        }

        return [
            'count' => $count,
            'averages' => [
                'registration_ease' => round((float) (clone $feedbacks)->avg('rating_registration_ease'), 2),
                'info_clarity' => round((float) (clone $feedbacks)->avg('rating_info_clarity'), 2),
                'tracking_ease' => round((float) (clone $feedbacks)->avg('rating_tracking_ease'), 2),
                'interview_experience' => round((float) (clone $feedbacks)->avg('rating_interview_experience'), 2),
                'staff_service' => round((float) (clone $feedbacks)->avg('rating_staff_service'), 2),
            ],
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
            'averages' => [
                'registration_ease' => null,
                'info_clarity' => null,
                'tracking_ease' => null,
                'interview_experience' => null,
                'staff_service' => null,
            ],
        ];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function periodOptions(): array
    {
        return RecruitmentPeriod::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name'])
            ->map(fn (RecruitmentPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->name,
            ])
            ->all();
    }
}

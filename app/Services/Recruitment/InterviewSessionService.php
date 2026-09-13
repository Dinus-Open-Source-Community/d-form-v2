<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class InterviewSessionService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $query = RecruitmentInterviewSession::query()
            ->with(['period:id,name', 'division:id,name,code'])
            ->withCount('interviews')
            ->orderByDesc('session_date')
            ->orderBy('starts_at');

        if (! empty($filters['period_id'])) {
            $query->where('recruitment_period_id', $filters['period_id']);
        }

        if (! empty($filters['division_id'])) {
            $query->where('recruitment_division_id', $filters['division_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RecruitmentInterviewSession
    {
        return RecruitmentInterviewSession::query()->create($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(RecruitmentInterviewSession $session): array
    {
        return [
            'id' => $session->id,
            'session_date' => $session->session_date?->toDateString(),
            'starts_at' => $this->formatTime($session->starts_at),
            'ends_at' => $this->formatTime($session->ends_at),
            'location' => $session->location,
            'room' => $session->room,
            'is_active' => $session->is_active,
            'interviews_count' => $session->interviews_count ?? $session->interviews()->count(),
            'period' => $session->period ? [
                'id' => $session->period->id,
                'name' => $session->period->name,
            ] : null,
            'division' => $session->division ? [
                'id' => $session->division->id,
                'name' => $session->division->name,
                'code' => $session->division->code,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toShowArray(RecruitmentInterviewSession $session): array
    {
        $session->loadMissing(['period', 'division', 'interviews.application', 'interviews.interviewer']);

        return [
            ...$this->toListArray($session),
            'notes' => $session->notes,
            'interviews' => $session->interviews
                ->sortBy('scheduled_at')
                ->values()
                ->map(fn ($interview): array => [
                    'id' => $interview->id,
                    'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
                    'location' => $interview->location,
                    'room' => $interview->room,
                    'status' => $interview->status instanceof \App\Enums\Recruitment\InterviewStatus
                        ? $interview->status->value
                        : (string) $interview->status,
                    'status_label' => $interview->status instanceof \App\Enums\Recruitment\InterviewStatus
                        ? $interview->status->label()
                        : (string) $interview->status,
                    'application' => [
                        'id' => $interview->application?->id,
                        'full_name' => $interview->application?->full_name,
                        'registration_number' => $interview->application?->registration_number,
                    ],
                    'interviewer' => $interview->interviewer ? [
                        'id' => $interview->interviewer->id,
                        'name' => $interview->interviewer->name,
                    ] : null,
                ])
                ->all(),
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

    private function formatTime(mixed $time): string
    {
        if ($time === null) {
            return '';
        }

        return substr((string) $time, 0, 5);
    }
}

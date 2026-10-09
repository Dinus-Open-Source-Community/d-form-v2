<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterviewSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class InterviewSessionService
{
    public function __construct(
        private readonly InterviewLifecycleService $lifecycle,
    ) {
    }
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): LengthAwarePaginator
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
     * @return list<array<string, mixed>>
     */
    public function todaySessions(?string $periodId = null): array
    {
        $query = RecruitmentInterviewSession::query()
            ->with(['period:id,name', 'division:id,name,code'])
            ->withCount('interviews')
            ->whereDate('session_date', today())
            ->orderBy('starts_at');

        if ($periodId !== null && $periodId !== '') {
            $query->where('recruitment_period_id', $periodId);
        }

        return $query
            ->get()
            ->map(fn (RecruitmentInterviewSession $session): array => $this->toListArray($session))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RecruitmentInterviewSession
    {
        $session = RecruitmentInterviewSession::query()->create($data);

        $this->backfillWaitingInterviews($session);

        return $session;
    }

    /**
     * Daftarkan applicant tahap Interview sedivisi yang belum punya
     * primary interview ke sesi yang baru dibuat.
     */
    private function backfillWaitingInterviews(RecruitmentInterviewSession $session): void
    {
        $targets = RecruitmentApplication::query()
            ->where('recruitment_period_id', $session->recruitment_period_id)
            ->where('primary_division_id', $session->recruitment_division_id)
            ->where('stage', ApplicationStage::Interview)
            ->whereDoesntHave('primaryInterview')
            ->get();

        if ($targets->isEmpty()) {
            return;
        }

        foreach ($targets as $application) {
            $this->lifecycle->createWaitingInterview($application, $session);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RecruitmentInterviewSession $session, array $data): RecruitmentInterviewSession
    {
        unset($data['recruitment_period_id']);

        return DB::transaction(function () use ($session, $data): RecruitmentInterviewSession {
            $session->update($data);

            $this->syncInterviews($session);

            return $session->refresh();
        });
    }

    /** Sinkron scheduled_at/location/room interview non-cancelled bila field sesi terkait berubah. */
    private function syncInterviews(RecruitmentInterviewSession $session): void
    {
        $sync = [];

        if ($session->wasChanged('session_date') || $this->startsAtChanged($session)) {
            $sync['scheduled_at'] = $this->buildScheduledAt($session);
        }

        foreach (['location', 'room'] as $field) {
            if ($session->wasChanged($field)) {
                $sync[$field] = $session->getAttribute($field);
            }
        }

        if ($sync === []) {
            return;
        }

        $session->interviews()
            ->where('status', '!=', InterviewStatus::Cancelled->value)
            ->update($sync);
    }

    /** Bandingkan jam mulai ternormalisasi H:i (DB menyimpan H:i:s, form mengirim H:i). */
    private function startsAtChanged(RecruitmentInterviewSession $session): bool
    {
        $normalize = fn (mixed $value): string => substr((string) $value, 0, 5);

        return $normalize($session->getOriginal('starts_at')) !== $normalize($session->starts_at);
    }

    /** scheduled_at = session_date + starts_at pada zona app.timezone. */
    private function buildScheduledAt(RecruitmentInterviewSession $session): Carbon
    {
        $date = $session->session_date?->format('Y-m-d');
        $time = substr((string) $session->starts_at, 0, 8);

        return Carbon::parse($date.' '.$time, config('app.timezone'));
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

    private function formatTime(mixed $time): string
    {
        if ($time === null) {
            return '';
        }

        return substr((string) $time, 0, 5);
    }
}

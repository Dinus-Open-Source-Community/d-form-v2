<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class MyInterviewService
{
    public function __construct(
        private readonly InterviewerApplicationPresenter $presenter,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForInterviewer(
        User $interviewer,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->with([
                'application.primaryDivision',
                'application.secondaryDivision',
                'application.queueEntry',
                'application.evaluation',
                'session.division',
            ]);

        $this->attendedApplicationScope($query);

        if (! empty($filters['queue'])) {
            $this->applyQueueFilter($query, (string) $filters['queue']);
        }

        if (! empty($filters['q']) && is_string($filters['q'])) {
            $search = trim($filters['q']);
            if ($search !== '') {
                $query->whereHas('application', function ($q) use ($search): void {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nim', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%");
                });
            }
        }

        if (! empty($filters['division_id']) && is_string($filters['division_id'])) {
            $divisionId = $filters['division_id'];
            $query->where(function ($q) use ($divisionId): void {
                $q->whereHas('session', fn ($sq) => $sq->where('recruitment_division_id', $divisionId))
                    ->orWhereHas('application', fn ($aq) => $aq->where('primary_division_id', $divisionId));
            });
        }

        if (! empty($filters['session_id']) && is_string($filters['session_id'])) {
            $query->where('recruitment_interview_session_id', $filters['session_id']);
        }

        if (! empty($filters['date_from']) && is_string($filters['date_from'])) {
            $dateFrom = $this->parseDateFilter($filters['date_from']);
            if ($dateFrom !== null) {
                $query->whereDate('scheduled_at', '>=', $dateFrom);
            }
        }

        if (! empty($filters['date_to']) && is_string($filters['date_to'])) {
            $dateTo = $this->parseDateFilter($filters['date_to']);
            if ($dateTo !== null) {
                $query->whereDate('scheduled_at', '<=', $dateTo);
            }
        }

        if (! empty($filters['eval']) && is_string($filters['eval'])) {
            $this->applyEvalFilter($query, $filters['eval']);
        }

        $this->applySort($query, $filters['sort'] ?? null);

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn (RecruitmentInterview $interview): array => $this->toListArray($interview));
    }

    /**
     * @return array<string, int>
     */
    public function queueCounts(User $interviewer): array
    {
        $dayStart = today()->startOfDay()->toDateTimeString();
        $nextDayStart = today()->addDay()->startOfDay()->toDateTimeString();

        $query = RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id);

        $this->attendedApplicationScope($query);

        $row = $query
            ->selectRaw(
                'COUNT(*) as all_count, '
                .'SUM(CASE WHEN EXISTS (SELECT 1 FROM recruitment_evaluations WHERE recruitment_evaluations.recruitment_application_id = recruitment_interviews.recruitment_application_id) THEN 0 ELSE 1 END) as pending_count, '
                .'SUM(CASE WHEN scheduled_at >= ? AND scheduled_at < ? THEN 1 ELSE 0 END) as today_count, '
                .'SUM(CASE WHEN EXISTS (SELECT 1 FROM recruitment_evaluations WHERE recruitment_evaluations.recruitment_application_id = recruitment_interviews.recruitment_application_id) THEN 1 ELSE 0 END) as done_count',
                [$dayStart, $nextDayStart]
            )
            ->first();

        return [
            'all' => (int) ($row?->getAttribute('all_count') ?? 0),
            'pending' => (int) ($row?->getAttribute('pending_count') ?? 0),
            'today' => (int) ($row?->getAttribute('today_count') ?? 0),
            'done' => (int) ($row?->getAttribute('done_count') ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function todaySessionsForInterviewer(User $interviewer): array
    {
        return RecruitmentInterviewSession::query()
            ->whereDate('session_date', today())
            ->whereHas('interviews', function ($q) use ($interviewer): void {
                $q->where('interviewer_id', $interviewer->id);
                $this->attendedApplicationScope($q);
            })
            ->with(['division:id,name', 'period:id,name'])
            ->withCount([
                'interviews as my_interviews_count' => function ($q) use ($interviewer): void {
                    $q->where('interviewer_id', $interviewer->id);
                    $this->attendedApplicationScope($q);
                },
            ])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (RecruitmentInterviewSession $session): array => [
                'id' => $session->id,
                'session_date' => $session->session_date?->toDateString(),
                'starts_at' => $this->formatTime($session->starts_at),
                'ends_at' => $this->formatTime($session->ends_at),
                'location' => $session->location,
                'room' => $session->room,
                'my_interviews_count' => $session->my_interviews_count ?? 0,
                'division' => $session->division ? [
                    'name' => $session->division->name,
                ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function nextActionForInterviewer(User $interviewer): ?array
    {
        $interview = RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->whereHas('application', fn ($q) => $this->pendingEvaluationScope($q))
            ->whereNotNull('scheduled_at');

        $this->attendedApplicationScope($interview);

        $interview = $interview
            ->where('scheduled_at', '<=', now())
            ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scheduled_at')
            ->with(['application', 'session'])
            ->first();

        if ($interview?->application === null) {
            return null;
        }

        $application = $interview->application;

        return [
            'title' => 'Applicant perlu dinilai',
            'description' => 'Lengkapi penilaian interview sebelum melanjut ke applicant berikutnya.',
            'application_id' => $application->id,
            'full_name' => $application->full_name,
            'registration_number' => $application->registration_number,
            'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
            'session_id' => $interview->session?->id,
        ];
    }

    /**
     * @return list<string>
     */
    public function sessionIdsForInterviewer(User $interviewer): array
    {
        $query = RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id);

        $this->attendedApplicationScope($query);

        return $query
            ->distinct()
            ->pluck('recruitment_interview_session_id')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function sessionsForInterviewer(User $interviewer): array
    {
        return RecruitmentInterviewSession::query()
            ->whereHas('interviews', function ($q) use ($interviewer): void {
                $q->where('interviewer_id', $interviewer->id);
                $this->attendedApplicationScope($q);
            })
            ->with(['division:id,name'])
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (RecruitmentInterviewSession $session): array => [
                'value' => $session->id,
                'label' => $this->formatSessionOptionLabel($session),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function divisionsForInterviewer(User $interviewer): array
    {
        return RecruitmentDivision::query()
            ->where(function ($q) use ($interviewer): void {
                $q->whereExists(function ($sq) use ($interviewer): void {
                    $sq->selectRaw('1')
                        ->from('recruitment_interview_sessions')
                        ->join('recruitment_interviews', 'recruitment_interviews.recruitment_interview_session_id', '=', 'recruitment_interview_sessions.id')
                        ->whereColumn('recruitment_interview_sessions.recruitment_division_id', 'recruitment_divisions.id')
                        ->where('recruitment_interviews.interviewer_id', $interviewer->id)
                        ->whereExists(function ($attendance): void {
                            $attendance->selectRaw('1')
                                ->from('recruitment_attendances')
                                ->whereColumn('recruitment_attendances.recruitment_application_id', 'recruitment_interviews.recruitment_application_id');
                        });
                })->orWhereExists(function ($aq) use ($interviewer): void {
                    $aq->selectRaw('1')
                        ->from('recruitment_applications')
                        ->join('recruitment_interviews', 'recruitment_interviews.recruitment_application_id', '=', 'recruitment_applications.id')
                        ->whereColumn('recruitment_applications.primary_division_id', 'recruitment_divisions.id')
                        ->where('recruitment_interviews.interviewer_id', $interviewer->id)
                        ->whereExists(function ($attendance): void {
                            $attendance->selectRaw('1')
                                ->from('recruitment_attendances')
                                ->whereColumn('recruitment_attendances.recruitment_application_id', 'recruitment_applications.id');
                        });
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (RecruitmentDivision $division): array => [
                'value' => $division->id,
                'label' => $division->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(RecruitmentInterview $interview): array
    {
        $application = $interview->application;
        $evaluation = $application?->evaluation;
        $needsEvaluation = $application !== null && $evaluation === null;

        return [
            'interview_id' => $interview->id,
            'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
            'status' => $interview->status->value,
            'status_label' => $interview->status->label(),
            'location' => $interview->location,
            'room' => $interview->room,
            'needs_evaluation' => $needsEvaluation,
            'application' => $application ? [
                'id' => $application->id,
                'full_name' => $application->full_name,
                'registration_number' => $application->registration_number,
                'nim' => $application->nim,
                'semester' => $application->semester,
                'primary_division' => $application->primaryDivision?->name,
            ] : null,
            'queue_number' => $application?->queueEntry?->queue_number,
            'has_evaluation' => $evaluation !== null,
            'evaluation_locked' => $evaluation?->isLocked() ?? false,
            'session' => $interview->session ? [
                'id' => $interview->session->id,
                'session_date' => $interview->session->session_date?->toDateString(),
                'division' => $interview->session->division?->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toShowArray(RecruitmentApplication $application): array
    {
        return $this->presenter->present($application);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function pendingEvaluationScope(Builder $query): void
    {
        $query->whereDoesntHave('evaluation');
    }

    /**
     * Batasi ke interview yang applicant-nya sudah regis ulang (punya baris attendance).
     *
     * @param  Builder<RecruitmentInterview>  $query
     */
    private function attendedApplicationScope(Builder $query): void
    {
        $query->whereHas('application.attendance');
    }

    /**
     * @param  Builder<RecruitmentInterview>  $query
     */
    private function applyEvalFilter(Builder $query, string $eval): void
    {
        match ($eval) {
            'pending' => $query->whereHas('application', fn ($q) => $this->pendingEvaluationScope($q)),
            'done' => $query->whereHas('application.evaluation'),
            'locked' => $query->whereHas('application.evaluation', fn ($q) => $q->whereNotNull('locked_at')),
            default => null,
        };
    }

    /**
     * @param  Builder<RecruitmentInterview>  $query
     */
    private function applySort(Builder $query, mixed $sort): void
    {
        $key = is_string($sort) ? $sort : '';

        match ($key) {
            'pending_first' => $query
                ->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM recruitment_evaluations WHERE recruitment_evaluations.recruitment_application_id = recruitment_interviews.recruitment_application_id) THEN 1 ELSE 0 END')
                ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('scheduled_at'),
            'queue_number', 'queue' => $query
                ->orderByRaw('CASE WHEN (SELECT queue_number FROM recruitment_queue_entries WHERE recruitment_queue_entries.recruitment_application_id = recruitment_interviews.recruitment_application_id) IS NULL THEN 1 ELSE 0 END')
                ->orderByRaw('(SELECT queue_number FROM recruitment_queue_entries WHERE recruitment_queue_entries.recruitment_application_id = recruitment_interviews.recruitment_application_id) ASC'),
            'name' => $query
                ->orderByRaw('(SELECT full_name FROM recruitment_applications WHERE recruitment_applications.id = recruitment_interviews.recruitment_application_id) ASC'),
            'schedule' => $this->applyDefaultOrder($query),
            default => $this->applyDefaultOrder($query),
        };
    }

    /**
     * @param  Builder<RecruitmentInterview>  $query
     */
    private function applyDefaultOrder(Builder $query): void
    {
        $query
            ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scheduled_at');
    }

    private function parseDateFilter(string $value): ?string
    {
        $value = trim($value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return $value;
    }

    /**
     * @param  Builder<RecruitmentInterview>  $query
     */
    private function applyQueueFilter(Builder $query, string $queue): void
    {
        match ($queue) {
            'pending' => $query->whereHas('application', fn ($q) => $this->pendingEvaluationScope($q)),
            'today' => $query->whereDate('scheduled_at', today()),
            'done' => $query->whereHas('application.evaluation'),
            default => null,
        };
    }

    private function formatSessionOptionLabel(RecruitmentInterviewSession $session): string
    {
        $date = $session->session_date?->format('d M') ?? '-';
        $division = $session->division?->name ?? '-';
        $place = $session->room !== '' && $session->room !== null
            ? "{$session->location}/{$session->room}"
            : $session->location;

        return "{$date} · {$division} · {$place}";
    }

    private function formatTime(mixed $time): string
    {
        if (! is_string($time) || $time === '') {
            return '';
        }

        return strlen($time) >= 5 ? substr($time, 0, 5) : $time;
    }
}

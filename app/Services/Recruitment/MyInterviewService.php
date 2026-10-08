<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorInstance;

final class MyInterviewService
{
    public function __construct(
        private readonly InterviewerApplicationPresenter $presenter,
        private readonly InterviewLifecycleService $lifecycle,
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
        $tab = is_string($filters['tab'] ?? null) ? (string) $filters['tab'] : 'in_progress';

        if ($tab === 'waiting') {
            return new PaginatorInstance([], 0, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        $query = RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->where('interview_kind', RecruitmentInterview::KIND_PRIMARY)
            ->with([
                'application.primaryDivision',
                'application.secondaryDivision',
                'application.attendance',
                'application.primaryInterview.evaluation',
                'evaluation',
                'session.division',
            ]);

        // Tampil-semua: tanpa attendance tetap tampil (terkunci via policy show/evaluate); flag has_attendance jadi penanda.
        $this->openSessionScope($query);
        $this->startedScope($query);

        match ($tab) {
            'done' => $query->whereHas('application.evaluations'),
            'in_progress' => $query
                ->whereIn('status', [InterviewStatus::Waiting, InterviewStatus::InProgress])
                ->whereHas('application', fn ($q) => $this->pendingEvaluationScope($q)),
            default => null,
        };

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
            $query->whereHas('session', fn ($sq) => $sq->where('recruitment_division_id', $divisionId));
        }

        if (! empty($filters['session_id']) && is_string($filters['session_id'])) {
            $query->where('recruitment_interview_session_id', $filters['session_id']);
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
    public function tabCounts(User $interviewer): array
    {
        $inProgressQuery = RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->where('interview_kind', RecruitmentInterview::KIND_PRIMARY)
            ->whereIn('status', [InterviewStatus::Waiting, InterviewStatus::InProgress])
            ->whereHas('application', fn ($q) => $this->pendingEvaluationScope($q));
        $this->openSessionScope($inProgressQuery);
        $inProgress = $inProgressQuery->count();

        $doneQuery = RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->where('interview_kind', RecruitmentInterview::KIND_PRIMARY)
            ->whereHas('application.evaluations');
        $this->openSessionScope($doneQuery);
        $done = $doneQuery->count();

        return [
            'in_progress' => $inProgress,
            'done' => $done,
        ];
    }

    /**
     * Hitung interview sesi terbuka yang jadwalnya belum mulai.
     */
    public function countPendingStart(User $interviewer): int
    {
        $query = RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id);

        $this->openSessionScope($query);

        return $query
            ->where(fn ($pending) => $pending->whereNull('scheduled_at')->orWhere('scheduled_at', '>', now()))
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function todaySessionsForInterviewer(User $interviewer): array
    {
        $divisionIds = RecruitmentInterviewerDivision::query()
            ->where('user_id', $interviewer->id)
            ->pluck('recruitment_division_id');

        return RecruitmentInterviewSession::query()
            ->whereDate('session_date', today())
            ->whereIn('recruitment_division_id', $divisionIds)
            ->where('is_active', true)
            ->with(['division:id,name', 'period:id,name'])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (RecruitmentInterviewSession $session): array => [
                'id' => $session->id,
                'session_date' => $session->session_date?->toDateString(),
                'starts_at' => $this->formatTime($session->starts_at),
                'ends_at' => $this->formatTime($session->ends_at),
                'location' => $session->location,
                'room' => $session->room,
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
            ->where('status', InterviewStatus::InProgress)
            ->whereHas('application', fn ($q) => $this->pendingEvaluationScope($q));

        $this->attendedApplicationScope($interview);
        $this->openSessionScope($interview);

        $interview = $interview
            ->orderBy('booked_at')
            ->with(['application', 'session'])
            ->first();

        if ($interview?->application === null) {
            return null;
        }

        $application = $interview->application;

        return [
            'title' => 'Applicant perlu dinilai',
            'description' => 'Lengkapi penilaian interview sebelum booking applicant berikutnya.',
            'application_id' => $application->id,
            'full_name' => $application->full_name,
            'registration_number' => $application->registration_number,
            'booked_at' => $interview->booked_at?->toIso8601String(),
            'session_id' => $interview->session?->id,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function sessionsForInterviewer(User $interviewer): array
    {
        $divisionIds = RecruitmentInterviewerDivision::query()
            ->where('user_id', $interviewer->id)
            ->pluck('recruitment_division_id');

        return RecruitmentInterviewSession::query()
            ->whereIn('recruitment_division_id', $divisionIds)
            ->where('is_active', true)
            ->whereDate('session_date', '>=', today())
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
                        ->where('recruitment_interview_sessions.is_active', true);
                })->orWhereExists(function ($aq) use ($interviewer): void {
                    $aq->selectRaw('1')
                        ->from('recruitment_applications')
                        ->join('recruitment_interviews', 'recruitment_interviews.recruitment_application_id', '=', 'recruitment_applications.id')
                        ->join('recruitment_interview_sessions', 'recruitment_interview_sessions.id', '=', 'recruitment_interviews.recruitment_interview_session_id')
                        ->whereColumn('recruitment_applications.primary_division_id', 'recruitment_divisions.id')
                        ->where('recruitment_interviews.interviewer_id', $interviewer->id)
                        ->where('recruitment_interview_sessions.is_active', true);
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
     * Peluang interview secondary untuk interviewer: applicant eligible yang
     * secondary-nya termasuk divisi interviewer, beserta sesi aktif divisinya.
     *
     * @return list<array{application: array{id: string, full_name: string, registration_number: string, nim: string, secondary_division: string|null}, sessions: list<array{value: string, label: string}>}>
     */
    public function secondaryOpportunitiesForInterviewer(User $interviewer): array
    {
        $divisionIds = RecruitmentInterviewerDivision::query()
            ->where('user_id', $interviewer->id)
            ->pluck('recruitment_division_id');

        if ($divisionIds->isEmpty()) {
            return [];
        }

        $applications = RecruitmentApplication::query()
            ->whereIn('secondary_division_id', $divisionIds)
            ->where('stage', '!=', ApplicationStage::Completed)
            ->whereHas('primaryInterview.evaluation', fn (Builder $query) => $query->where('save_count', '>=', 1))
            ->whereDoesntHave('secondaryInterview')
            ->with(['secondaryDivision:id,name'])
            ->orderBy('full_name')
            ->get()
            ->filter(fn (RecruitmentApplication $application): bool => $this->lifecycle->secondaryEligible($application))
            ->values();

        return $applications->map(function (RecruitmentApplication $application): array {
            $sessions = RecruitmentInterviewSession::query()
                ->where('recruitment_period_id', $application->recruitment_period_id)
                ->where('recruitment_division_id', $application->secondary_division_id)
                ->where('is_active', true)
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

            return [
                'application' => [
                    'id' => $application->id,
                    'full_name' => $application->full_name,
                    'registration_number' => $application->registration_number,
                    'nim' => $application->nim,
                    'secondary_division' => $application->secondaryDivision?->name,
                ],
                'sessions' => $sessions,
            ];
        })->values()->all();
    }

    /**
     * Interview secondary yang sudah diklaim interviewer, masing-masing
     * membawa status evaluasinya sendiri.
     *
     * @return list<array<string, mixed>>
     */
    public function claimedSecondaryForInterviewer(User $interviewer): array
    {
        return RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->where('interview_kind', RecruitmentInterview::KIND_SECONDARY)
            ->whereDoesntHave('evaluation')
            ->with([
                'application.primaryDivision',
                'application.secondaryDivision',
                'application.attendance',
                'evaluation',
                'session.division',
            ])
            ->orderByDesc('booked_at')
            ->get()
            ->map(fn (RecruitmentInterview $interview): array => $this->toListArray($interview))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(RecruitmentInterview $interview): array
    {
        $application = $interview->application;
        $evaluation = $interview->evaluation;
        $needsEvaluation = $evaluation === null;

        return [
            'interview_id' => $interview->id,
            'booked_at' => $interview->booked_at?->toIso8601String(),
            'checked_in_at' => $application?->attendance?->checked_in_at?->toIso8601String(),
            'status' => $interview->status->value,
            'status_label' => $interview->status->label(),
            'location' => $interview->location,
            'room' => $interview->room,
            'needs_evaluation' => $needsEvaluation,
            'has_attendance' => $application?->attendance !== null,
            'application' => $application ? [
                'id' => $application->id,
                'full_name' => $application->full_name,
                'registration_number' => $application->registration_number,
                'nim' => $application->nim,
                'semester' => $application->semester,
                'primary_division' => $application->primaryDivision?->name,
            ] : null,
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
    public function toShowArray(RecruitmentInterview $interview): array
    {
        return $this->presenter->presentForInterview($interview);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function pendingEvaluationScope(Builder $query): void
    {
        $query->whereDoesntHave('evaluations');
    }

    /**
     * Batasi ke interview yang waktunya sudah mulai (scheduled_at lewat).
     *
     * @param  Builder<RecruitmentInterview>  $query
     */
    private function startedScope(Builder $query): void
    {
        $query->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now());
    }

    /**
     * Batasi ke interview yang sesinya sudah dibuka (is_active true).
     *
     * @param  Builder<RecruitmentInterview>  $query
     */
    private function openSessionScope(Builder $query): void
    {
        $query->whereHas('session', fn ($session) => $session->where('is_active', true));
    }

    /**
     * Saran next-action hanya untuk applicant actionable (sudah regis ulang).
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
            'done' => $query->whereHas('application.evaluations'),
            'locked' => $query->whereHas('application.evaluations', fn ($q) => $q->whereNotNull('locked_at')),
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
                ->orderBy('booked_at'),
            'name' => $query
                ->orderByRaw('(SELECT full_name FROM recruitment_applications WHERE recruitment_applications.id = recruitment_interviews.recruitment_application_id) ASC'),
            'check_in' => $query
                ->orderByRaw('(SELECT checked_in_at FROM recruitment_attendances WHERE recruitment_attendances.recruitment_application_id = recruitment_interviews.recruitment_application_id) ASC'),
            default => $query->orderByDesc('booked_at'),
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

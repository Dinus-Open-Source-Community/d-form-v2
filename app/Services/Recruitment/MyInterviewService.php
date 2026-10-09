<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
        $tab = is_string($filters['tab'] ?? null) ? (string) $filters['tab'] : 'waiting';

        if ($tab === 'waiting') {
            return $this->paginateWaiting($interviewer, $filters, $page, $perPage);
        }

        if ($tab === 'all') {
            return $this->paginateAll($interviewer, $filters, $page, $perPage);
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
                'session.period:id,name',
            ]);

        // Tampil-semua: tanpa attendance tetap tampil (terkunci via policy show/evaluate); flag has_attendance jadi penanda.
        $this->openSessionScope($query);
        $this->startedScope($query);

        match ($tab) {
            'done' => $query->whereHas('application.evaluations'),
            'in_progress' => $query
                ->where('status', InterviewStatus::InProgress)
                ->whereHas('application', fn ($q) => $this->pendingEvaluationScope($q)),
            default => null,
        };

        if (! empty($filters['q']) && is_string($filters['q'])) {
            $search = trim($filters['q']);
            if ($search !== '') {
                $query->whereHas('application', function ($q) use ($search): void {
                    $this->whereLike($q, 'full_name', $search);
                    $this->whereLike($q, 'nim', $search, 'or');
                    $this->whereLike($q, 'registration_number', $search, 'or');
                });
            }
        }

        if (! empty($filters['division_id']) && is_string($filters['division_id'])) {
            $divisionId = $filters['division_id'];
            $query->whereHas('session', fn ($sq) => $sq->where('recruitment_division_id', $divisionId));
        }

        $this->applyPeriodScope($query, $filters['period_id'] ?? null);

        $this->applyDateSessionScope(
            $query,
            $filters['session_id'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['show_all'] ?? null,
        );

        if (! empty($filters['eval']) && is_string($filters['eval'])) {
            $this->applyEvalFilter($query, $filters['eval']);
        }

        $this->applySort($query, $filters['sort'] ?? null);

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn (RecruitmentInterview $interview): array => $this->toListArray($interview));
    }

    /**
     * Tab Antrean: pool klaim — menunggu + sudah regis ulang + belum
     * bertuan + divisi saya + sesi aktif. Default lingkup sesi hari ini
     * dan tanpa filter milik.
     *
     * @param  array<string, mixed>  $filters
     */
    private function paginateWaiting(
        User $interviewer,
        array $filters,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $query = RecruitmentInterview::query()
            ->with([
                'application.primaryDivision',
                'application.secondaryDivision',
                'application.attendance',
                'application.primaryInterview.evaluation',
                'evaluation',
                'session.division',
                'session.period:id,name',
            ]);

        $this->applyWaitingScope($query, $interviewer);
        $this->applyDateSessionScope(
            $query,
            $filters['session_id'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['show_all'] ?? null,
        );

        if (! empty($filters['q']) && is_string($filters['q'])) {
            $search = trim($filters['q']);
            if ($search !== '') {
                $query->whereHas('application', function ($q) use ($search): void {
                    $this->whereLike($q, 'full_name', $search);
                    $this->whereLike($q, 'nim', $search, 'or');
                    $this->whereLike($q, 'registration_number', $search, 'or');
                });
            }
        }

        if (! empty($filters['division_id']) && is_string($filters['division_id'])) {
            $divisionId = $filters['division_id'];
            $query->whereHas('session', fn ($sq) => $sq->where('recruitment_division_id', $divisionId));
        }

        $this->applyPeriodScope($query, $filters['period_id'] ?? null);

        if (! empty($filters['eval']) && is_string($filters['eval'])) {
            $this->applyEvalFilter($query, $filters['eval']);
        }

        $this->applySort($query, $filters['sort'] ?? null);

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn (RecruitmentInterview $interview): array => $this->toListArray($interview));
    }

    /**
     * Tab Semua Peserta: applicant tahap Interview di divisi saya,
     * digabung ke primary interview bila ada. Default lingkup sesi hari
     * ini; show_all / session_id / date_from-to membuka cakupan.
     *
     * @param  array<string, mixed>  $filters
     */
    private function paginateAll(
        User $interviewer,
        array $filters,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $query = $this->allApplicationsQuery($interviewer)
            ->with([
                'primaryDivision',
                'secondaryDivision',
                'attendance',
                'primaryInterview.evaluation',
                'primaryInterview.session.division',
                'primaryInterview.session.period:id,name',
            ]);

        if (! empty($filters['q']) && is_string($filters['q'])) {
            $search = trim($filters['q']);
            if ($search !== '') {
                $query->where(function ($q) use ($search): void {
                    $this->whereLike($q, 'full_name', $search);
                    $this->whereLike($q, 'nim', $search, 'or');
                    $this->whereLike($q, 'registration_number', $search, 'or');
                });
            }
        }

        if (! empty($filters['division_id']) && is_string($filters['division_id'])) {
            $query->where('primary_division_id', $filters['division_id']);
        }

        if (! empty($filters['period_id']) && is_string($filters['period_id'])) {
            $query->where('recruitment_period_id', $filters['period_id']);
        }

        $sessionId = $filters['session_id'] ?? null;
        $this->applyDateSessionScopeForApplications(
            $query,
            $sessionId,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['show_all'] ?? null,
        );

        if (! empty($filters['eval']) && is_string($filters['eval'])) {
            match ($filters['eval']) {
                'pending' => $query->whereDoesntHave('evaluations'),
                'done' => $query->whereHas('evaluations'),
                'locked' => $query->whereHas('evaluations', fn ($q) => $q->whereNotNull('locked_at')),
                default => null,
            };
        }

        $sort = is_string($filters['sort'] ?? null) ? (string) $filters['sort'] : '';

        match ($sort) {
            'pending_first' => $query
                ->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM recruitment_evaluations WHERE recruitment_evaluations.recruitment_application_id = recruitment_applications.id) THEN 1 ELSE 0 END')
                ->orderBy('registration_number'),
            'name' => $query->orderBy('full_name'),
            'check_in' => $query
                ->orderByRaw('(SELECT checked_in_at FROM recruitment_attendances WHERE recruitment_attendances.recruitment_application_id = recruitment_applications.id) ASC'),
            default => $query->orderBy('registration_number'),
        };

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn (RecruitmentApplication $application): array => $this->toAllRowArray($application));
    }

    /**
     * Kriteria pool klaim yang sama untuk tab Antrean dan badge-nya.
     *
     * @param  Builder<RecruitmentInterview>  $query
     */
    private function applyWaitingScope(Builder $query, User $interviewer): void
    {
        $divisionIds = RecruitmentInterviewerDivision::query()
            ->where('user_id', $interviewer->id)
            ->pluck('recruitment_division_id');

        $query
            ->where('interview_kind', RecruitmentInterview::KIND_PRIMARY)
            ->where('status', InterviewStatus::Waiting)
            ->whereNull('interviewer_id')
            ->whereHas('application', fn ($q) => $q
                ->where('stage', ApplicationStage::Interview)
                ->whereNull('cancelled_at')
                ->whereHas('attendance'))
            ->whereHas('session', fn ($sq) => $sq
                ->where('is_active', true)
                ->whereIn('recruitment_division_id', $divisionIds));
    }

    /**
     * Basis tab/badge Semua Peserta.
     *
     * @return Builder<RecruitmentApplication>
     */
    private function allApplicationsQuery(User $interviewer): Builder
    {
        $divisionIds = RecruitmentInterviewerDivision::query()
            ->where('user_id', $interviewer->id)
            ->pluck('recruitment_division_id');

        return RecruitmentApplication::query()
            ->where('stage', ApplicationStage::Interview)
            ->whereNull('cancelled_at')
            ->whereIn('primary_division_id', $divisionIds);
    }

    /**
     * Baris pantau tab Semua Peserta, dibentuk seperti toListArray.
     * Tanpa interview: terkunci (has_attendance false bila belum scan).
     *
     * @return array<string, mixed>
     */
    private function toAllRowArray(RecruitmentApplication $application): array
    {
        $interview = $application->primaryInterview;
        $evaluation = $interview?->evaluation;

        return [
            'interview_id' => $interview?->id ?? '',
            'scheduled_at' => $interview?->scheduled_at?->toIso8601String(),
            'status' => $interview?->status->value ?? InterviewStatus::Waiting->value,
            'status_label' => $interview?->status->label() ?? 'Belum ada interview',
            'interview_kind' => $interview !== null ? (string) $interview->interview_kind : RecruitmentInterview::KIND_PRIMARY,
            'location' => $interview?->location ?? '',
            'room' => $interview?->room ?? '',
            'needs_evaluation' => false,
            'has_attendance' => $application->attendance !== null,
            'application' => [
                'id' => $application->id,
                'full_name' => $application->full_name,
                'registration_number' => $application->registration_number,
                'nim' => $application->nim,
                'semester' => $application->semester,
                'primary_division' => $application->primaryDivision?->name,
                'secondary_division' => $application->secondaryDivision?->name,
            ],
            'has_evaluation' => $evaluation !== null,
            'evaluation_locked' => $evaluation?->isLocked() ?? false,
            'evaluation_recommendation' => $evaluation?->recommendation?->value,
            'session' => $interview?->session ? [
                'id' => $interview->session->id,
                'session_date' => $interview->session->session_date?->toDateString(),
                'division' => $interview->session->division?->name,
                'period' => $interview->session->period?->name,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function tabCounts(User $interviewer, array $filters = []): array
    {
        $sessionId = $filters['session_id'] ?? null;

        $waitingQuery = RecruitmentInterview::query();
        $this->applyWaitingScope($waitingQuery, $interviewer);
        $this->applyPeriodScope($waitingQuery, $filters['period_id'] ?? null);
        $this->applyDateSessionScope(
            $waitingQuery,
            $sessionId,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['show_all'] ?? null,
        );
        $waiting = $waitingQuery->count();

        $allQuery = $this->allApplicationsQuery($interviewer);
        if (! empty($filters['period_id']) && is_string($filters['period_id'])) {
            $allQuery->where('recruitment_period_id', $filters['period_id']);
        }
        $this->applyDateSessionScopeForApplications(
            $allQuery,
            $sessionId,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['show_all'] ?? null,
        );
        $all = $allQuery->count();

        $inProgressQuery = RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->where('interview_kind', RecruitmentInterview::KIND_PRIMARY)
            ->where('status', InterviewStatus::InProgress)
            ->whereHas('application', fn ($q) => $this->pendingEvaluationScope($q));
        $this->openSessionScope($inProgressQuery);
        $this->startedScope($inProgressQuery);
        $this->applyPeriodScope($inProgressQuery, $filters['period_id'] ?? null);
        $this->applyDateSessionScope(
            $inProgressQuery,
            $filters['session_id'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['show_all'] ?? null,
        );
        $inProgress = $inProgressQuery->count();

        $doneQuery = RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->where('interview_kind', RecruitmentInterview::KIND_PRIMARY)
            ->whereHas('application.evaluations');
        $this->openSessionScope($doneQuery);
        $this->startedScope($doneQuery);
        $this->applyPeriodScope($doneQuery, $filters['period_id'] ?? null);
        $this->applyDateSessionScope(
            $doneQuery,
            $filters['session_id'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['show_all'] ?? null,
        );
        $done = $doneQuery->count();

        return [
            'waiting' => $waiting,
            'all' => $all,
            'in_progress' => $inProgress,
            'done' => $done,
        ];
    }

    /**
     * Hitung interview sesi terbuka yang jadwalnya belum mulai,
     * dalam lingkup tanggal/sesi yang sedang terlihat.
     *
     * @param  array<string, mixed>  $filters
     */
    public function countPendingStart(User $interviewer, array $filters = []): int
    {
        $query = RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->where('interview_kind', RecruitmentInterview::KIND_PRIMARY);

        $this->openSessionScope($query);
        $this->applyPeriodScope($query, $filters['period_id'] ?? null);
        $this->applyDateSessionScope(
            $query,
            $filters['session_id'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['show_all'] ?? null,
        );

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
            ->whereDate('session_date', '>=', today()->subDays(7)->toDateString())
            ->whereDate('session_date', '<=', today()->addDays(30)->toDateString())
            ->with(['division:id,name'])
            ->orderByDesc('session_date')
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
    public function periodsForInterviewer(User $interviewer): array
    {
        $divisionIds = RecruitmentInterviewerDivision::query()
            ->where('user_id', $interviewer->id)
            ->pluck('recruitment_division_id');

        return RecruitmentPeriod::query()
            ->whereExists(function ($q) use ($divisionIds): void {
                $q->selectRaw('1')
                    ->from('recruitment_interview_sessions')
                    ->whereColumn('recruitment_interview_sessions.recruitment_period_id', 'recruitment_periods.id')
                    ->whereIn('recruitment_interview_sessions.recruitment_division_id', $divisionIds);
            })
            ->orderByDesc('created_at')
            ->get(['id', 'name'])
            ->map(fn (RecruitmentPeriod $period): array => [
                'value' => $period->id,
                'label' => $period->name,
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
     * @param  array<string, mixed>  $filters
     * @return list<array{application: array{id: string, full_name: string, registration_number: string, nim: string, secondary_division: string|null}, sessions: list<array{value: string, label: string, period: string|null}>}>
     */
    public function secondaryOpportunitiesForInterviewer(User $interviewer, array $filters = []): array
    {
        $divisionIds = RecruitmentInterviewerDivision::query()
            ->where('user_id', $interviewer->id)
            ->pluck('recruitment_division_id');

        if ($divisionIds->isEmpty()) {
            return [];
        }

        $divisionFilter = $filters['division_id'] ?? null;
        $divisionFilter = is_string($divisionFilter) && trim($divisionFilter) !== '' ? $divisionFilter : null;
        $sessionFilter = $filters['session_id'] ?? null;
        $sessionFilter = is_string($sessionFilter) && trim($sessionFilter) !== '' ? $sessionFilter : null;
        $periodFilter = $filters['period_id'] ?? null;
        $periodFilter = is_string($periodFilter) && trim($periodFilter) !== '' ? $periodFilter : null;
        $search = $filters['q'] ?? null;
        $search = is_string($search) && trim($search) !== '' ? trim($search) : null;

        $applications = RecruitmentApplication::query()
            ->when($divisionFilter !== null, fn ($query) => $query->where('secondary_division_id', $divisionFilter), fn ($query) => $query->whereIn('secondary_division_id', $divisionIds))
            ->when($periodFilter !== null, fn ($query) => $query->where('recruitment_period_id', $periodFilter))
            ->where('stage', '!=', ApplicationStage::Completed)
            ->whereHas('attendance')
            ->when($search !== null, fn ($query) => $query->where(function ($q) use ($search): void {
                $this->whereLike($q, 'full_name', $search);
                $this->whereLike($q, 'nim', $search, 'or');
                $this->whereLike($q, 'registration_number', $search, 'or');
            }))
            ->whereHas('primaryInterview.evaluation', fn (Builder $query) => $query->where('save_count', '>=', 1))
            ->whereDoesntHave('secondaryInterview')
            ->with(['secondaryDivision:id,name'])
            ->orderBy('full_name')
            ->get()
            ->filter(fn (RecruitmentApplication $application): bool => $this->lifecycle->secondaryEligible($application))
            ->values();

        // Satu query sesi untuk semua applicant (tanpa N+1).
        $sessionsByKey = $this->secondarySessionsByDivision($applications);

        return $applications->map(function (RecruitmentApplication $application) use ($sessionsByKey, $sessionFilter): ?array {
            $sessions = $sessionsByKey->get($application->recruitment_period_id.'|'.$application->secondary_division_id, collect())
                ->map(fn (RecruitmentInterviewSession $session): array => [
                    'value' => $session->id,
                    'label' => $this->formatSessionOptionLabel($session),
                    'period' => $session->period?->name,
                ])
                ->values()
                ->all();

            if ($sessionFilter !== null) {
                $sessions = array_values(array_filter(
                    $sessions,
                    fn (array $session): bool => (string) $session['value'] === $sessionFilter
                ));

                if ($sessions === []) {
                    return null;
                }
            }

            return [
                'application' => [
                    'id' => $application->id,
                    'full_name' => $application->full_name,
                    'registration_number' => $application->registration_number,
                    'nim' => $application->nim,
                    'secondary_division' => $application->secondaryDivision?->name,
                    'primary_recommendation' => $application->primaryInterview?->evaluation?->recommendation?->value,
                ],
                'sessions' => $sessions,
            ];
        })
            ->filter(fn (?array $opportunity): bool => $opportunity !== null)
            ->values()
            ->all();
    }

    /**
     * Sesi aktif dikelompokkan per (periode, divisi) dalam 1 query.
     *
     * @param  \Illuminate\Support\Collection<int, RecruitmentApplication>  $applications
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, RecruitmentInterviewSession>>
     */
    private function secondarySessionsByDivision($applications)
    {
        if ($applications->isEmpty()) {
            return collect();
        }

        return RecruitmentInterviewSession::query()
            ->whereIn('recruitment_period_id', $applications->pluck('recruitment_period_id')->unique()->all())
            ->whereIn('recruitment_division_id', $applications->pluck('secondary_division_id')->unique()->all())
            ->where('is_active', true)
            ->with(['division:id,name', 'period:id,name'])
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (RecruitmentInterviewSession $session): string => $session->recruitment_period_id.'|'.$session->recruitment_division_id);
    }

    /**
     * Peluang interview primary untuk interviewer: applicant checked-in yang
     * interview primary-nya masih menunggu dan belum bertuan, pada sesi aktif
     * divisi interviewer.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{interview: array{id: string}, application: array{id: string, full_name: string, registration_number: string, nim: string, primary_division: string|null}, session: array{id: string, label: string, period: string|null}, interviewer_options: list<array{value: string, label: string}>}>
     */
    public function primaryOpportunitiesForInterviewer(User $interviewer, array $filters = []): array
    {
        $divisionIds = RecruitmentInterviewerDivision::query()
            ->where('user_id', $interviewer->id)
            ->pluck('recruitment_division_id');

        if ($divisionIds->isEmpty()) {
            return [];
        }

        $divisionFilter = $filters['division_id'] ?? null;
        $divisionFilter = is_string($divisionFilter) && trim($divisionFilter) !== '' ? $divisionFilter : null;
        $sessionFilter = $filters['session_id'] ?? null;
        $sessionFilter = is_string($sessionFilter) && trim($sessionFilter) !== '' ? $sessionFilter : null;
        $search = $filters['q'] ?? null;
        $search = is_string($search) && trim($search) !== '' ? trim($search) : null;

        $opportunities = RecruitmentInterview::query()
            ->where('interview_kind', RecruitmentInterview::KIND_PRIMARY)
            ->where('status', InterviewStatus::Waiting)
            ->whereNull('interviewer_id')
            ->when(
                $sessionFilter !== null,
                fn ($query) => $query->where('recruitment_interview_session_id', $sessionFilter),
                function ($query) use ($filters): void {
                    if ($this->isShowAll($filters['show_all'] ?? null)) {
                        return;
                    }

                    $from = $this->cleanDate($filters['date_from'] ?? null);
                    $to = $this->cleanDate($filters['date_to'] ?? null);

                    if ($from === null && $to === null) {
                        $query->whereHas('session', fn ($sq) => $sq->whereDate('session_date', today()));

                        return;
                    }

                    $query->whereHas('session', function ($sq) use ($from, $to): void {
                        if ($from !== null) {
                            $sq->whereDate('session_date', '>=', $from);
                        }
                        if ($to !== null) {
                            $sq->whereDate('session_date', '<=', $to);
                        }
                    });
                },
            )
            ->whereHas('application', function ($query) use ($search): void {
                $query
                    ->where('stage', ApplicationStage::Interview)
                    ->whereNull('cancelled_at')
                    ->whereHas('attendance');

                if ($search !== null) {
                    $query->where(function ($q) use ($search): void {
                        $this->whereLike($q, 'full_name', $search);
                        $this->whereLike($q, 'nim', $search, 'or');
                        $this->whereLike($q, 'registration_number', $search, 'or');
                    });
                }
            })
            ->whereHas('session', function ($query) use ($divisionIds, $divisionFilter, $filters): void {
                $query
                    ->where('is_active', true)
                    ->whereIn('recruitment_division_id', $divisionIds);

                if ($divisionFilter !== null) {
                    $query->where('recruitment_division_id', $divisionFilter);
                }

                $periodFilter = $filters['period_id'] ?? null;
                if (is_string($periodFilter) && trim($periodFilter) !== '') {
                    $query->where('recruitment_period_id', $periodFilter);
                }
            })
            ->with(['application.primaryDivision', 'session.division', 'session.period:id,name'])
            ->orderBy('scheduled_at')
            ->get()
            ->filter(fn (RecruitmentInterview $interview): bool => $interview->application !== null && $interview->session !== null)
            ->values();

        $interviewersByDivision = $this->interviewersByDivision(
            $opportunities->map(fn (RecruitmentInterview $interview): string => (string) $interview->session->recruitment_division_id)->unique()->values()->all()
        );

        return $opportunities->map(fn (RecruitmentInterview $interview): array => [
            'interview' => [
                'id' => $interview->id,
            ],
            'application' => [
                'id' => $interview->application->id,
                'full_name' => $interview->application->full_name,
                'registration_number' => $interview->application->registration_number,
                'nim' => $interview->application->nim,
                'primary_division' => $interview->application->primaryDivision?->name,
            ],
            'session' => [
                'id' => $interview->session->id,
                'label' => $this->formatSessionOptionLabel($interview->session),
                'period' => $interview->session->period?->name,
            ],
            'interviewer_options' => $interviewersByDivision[(string) $interview->session->recruitment_division_id] ?? [],
        ])
            ->values()
            ->all();
    }

    /**
     * Kandidat interviewer per divisi sesi (1 query + eager user, tanpa N+1).
     *
     * @param  list<string>  $divisionIds
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function interviewersByDivision(array $divisionIds): array
    {
        if ($divisionIds === []) {
            return [];
        }

        $grouped = RecruitmentInterviewerDivision::query()
            ->whereIn('recruitment_division_id', $divisionIds)
            ->with(['user:id,name'])
            ->get()
            ->groupBy('recruitment_division_id');

        $result = [];

        foreach ($grouped as $divisionId => $assignments) {
            $options = $assignments
                ->map(fn (RecruitmentInterviewerDivision $assignment): array => [
                    'value' => (string) $assignment->user_id,
                    'label' => (string) ($assignment->user?->name ?? ''),
                ])
                ->filter(fn (array $option): bool => $option['label'] !== '')
                ->sortBy('label')
                ->values()
                ->all();

            $result[(string) $divisionId] = $options;
        }

        return $result;
    }

    /**
     * Interview secondary yang sudah diklaim interviewer, masing-masing
     * membawa status evaluasinya sendiri.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function claimedSecondaryForInterviewer(User $interviewer, array $filters = []): array
    {
        $divisionFilter = $filters['division_id'] ?? null;
        $divisionFilter = is_string($divisionFilter) && trim($divisionFilter) !== '' ? $divisionFilter : null;
        $sessionFilter = $filters['session_id'] ?? null;
        $sessionFilter = is_string($sessionFilter) && trim($sessionFilter) !== '' ? $sessionFilter : null;
        $periodFilter = $filters['period_id'] ?? null;
        $periodFilter = is_string($periodFilter) && trim($periodFilter) !== '' ? $periodFilter : null;
        $search = $filters['q'] ?? null;
        $search = is_string($search) && trim($search) !== '' ? trim($search) : null;

        return RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->where('interview_kind', RecruitmentInterview::KIND_SECONDARY)
            ->whereDoesntHave('evaluation')
            ->when($sessionFilter !== null, fn ($query) => $query->where('recruitment_interview_session_id', $sessionFilter))
            ->when($periodFilter !== null, fn ($query) => $query->whereHas('session', fn ($sq) => $sq->where('recruitment_period_id', $periodFilter)))
            ->when($search !== null, fn ($query) => $query->whereHas('application', function ($q) use ($search): void {
                $this->whereLike($q, 'full_name', $search);
                $this->whereLike($q, 'nim', $search, 'or');
                $this->whereLike($q, 'registration_number', $search, 'or');
            }))
            ->when($divisionFilter !== null, fn ($query) => $query->whereHas('session', fn ($sq) => $sq->where('recruitment_division_id', $divisionFilter)))
            ->with([
                'application.primaryDivision',
                'application.secondaryDivision',
                'application.attendance',
                'evaluation',
                'session.division',
                'session.period:id,name',
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
            'interview_kind' => (string) $interview->interview_kind,
            'location' => $interview->location,
            'room' => $interview->room,
            'needs_evaluation' => $needsEvaluation,
            'has_attendance' => $application?->attendance !== null,
            'evaluation_recommendation' => $evaluation?->recommendation?->value,
            'application' => $application ? [
                'id' => $application->id,
                'full_name' => $application->full_name,
                'registration_number' => $application->registration_number,
                'nim' => $application->nim,
                'semester' => $application->semester,
                'primary_division' => $application->primaryDivision?->name,
                'secondary_division' => $application->secondaryDivision?->name,
            ] : null,
            'has_evaluation' => $evaluation !== null,
            'evaluation_locked' => $evaluation?->isLocked() ?? false,
            'session' => $interview->session ? [
                'id' => $interview->session->id,
                'session_date' => $interview->session->session_date?->toDateString(),
                'division' => $interview->session->division?->name,
                'period' => $interview->session->period?->name,
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
     * Lingkup tanggal/sesi yang terlihat: sesi terpilih bila ada,
     * rentang tanggal bila diminta, semua tanggal bila show_all,
     * sesi hari ini bila tidak. Cerminan pemfilteran paginateForInterviewer.
     *
     * @param  Builder<RecruitmentInterview>  $query
     */
    private function applyDateSessionScope(
        Builder $query,
        mixed $sessionId,
        mixed $dateFrom = null,
        mixed $dateTo = null,
        mixed $showAll = null,
    ): void {
        if (is_string($sessionId) && trim($sessionId) !== '') {
            $query->where('recruitment_interview_session_id', $sessionId);

            return;
        }

        if ($this->isShowAll($showAll)) {
            return;
        }

        $from = $this->cleanDate($dateFrom);
        $to = $this->cleanDate($dateTo);

        if ($from !== null || $to !== null) {
            $query->whereHas('session', function ($session) use ($from, $to): void {
                if ($from !== null) {
                    $session->whereDate('session_date', '>=', $from);
                }
                if ($to !== null) {
                    $session->whereDate('session_date', '<=', $to);
                }
            });

            return;
        }

        $query->whereHas('session', fn ($session) => $session->whereDate('session_date', today()));
    }

    /**
     * Cerminan applyDateSessionScope untuk query applicant (tab Semua):
     * applicant dihubungkan ke sesi lewat primaryInterview.
     * Applicant tanpa primaryInterview hanya tampil saat show_all.
     *
     * @param  Builder<RecruitmentApplication>  $query
     */
    private function applyDateSessionScopeForApplications(
        Builder $query,
        mixed $sessionId,
        mixed $dateFrom = null,
        mixed $dateTo = null,
        mixed $showAll = null,
    ): void {
        if (is_string($sessionId) && trim($sessionId) !== '') {
            $query->whereHas('primaryInterview', fn ($iq) => $iq->where('recruitment_interview_session_id', $sessionId));

            return;
        }

        if ($this->isShowAll($showAll)) {
            return;
        }

        $from = $this->cleanDate($dateFrom);
        $to = $this->cleanDate($dateTo);

        if ($from !== null || $to !== null) {
            $query->whereHas('primaryInterview.session', function ($session) use ($from, $to): void {
                if ($from !== null) {
                    $session->whereDate('session_date', '>=', $from);
                }
                if ($to !== null) {
                    $session->whereDate('session_date', '<=', $to);
                }
            });

            return;
        }

        $query->whereHas('primaryInterview.session', fn ($session) => $session->whereDate('session_date', today()));
    }

    private function isShowAll(mixed $showAll): bool
    {
        if (is_bool($showAll)) {
            return $showAll;
        }

        if (is_int($showAll)) {
            return $showAll === 1;
        }

        if (! is_string($showAll)) {
            return false;
        }

        return in_array(strtolower(trim($showAll)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Filter period untuk query interview (lewat sesi).
     *
     * @param  Builder<RecruitmentInterview>  $query
     */
    private function applyPeriodScope(Builder $query, mixed $periodId): void
    {
        if (is_string($periodId) && trim($periodId) !== '') {
            $query->whereHas('session', fn ($sq) => $sq->where('recruitment_period_id', $periodId));
        }
    }

    private function cleanDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * LIKE dengan ESCAPE eksplisit agar % _ \ dari input user diperlakukan
     * literal di MySQL maupun SQLite. Kolom selalu hardcoded di call-site.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function whereLike(Builder $query, string $column, string $search, string $boolean = 'and'): void
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        $query->whereRaw("{$column} LIKE ? ESCAPE '\\'", ["%{$escaped}%"], $boolean);
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

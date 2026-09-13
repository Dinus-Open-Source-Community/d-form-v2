<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
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
            ])
            ->orderBy('scheduled_at');

        if (! empty($filters['queue'])) {
            $this->applyQueueFilter($query, (string) $filters['queue']);
        }

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn (RecruitmentInterview $interview): array => $this->toListArray($interview));
    }

    /**
     * @return array<string, int>
     */
    public function queueCounts(User $interviewer): array
    {
        $base = RecruitmentInterview::query()->where('interviewer_id', $interviewer->id);

        return [
            'all' => (clone $base)->count(),
            'pending' => (clone $base)->whereHas('application', fn ($q) => $this->pendingEvaluationScope($q))->count(),
            'today' => (clone $base)->whereDate('scheduled_at', today())->count(),
            'done' => (clone $base)->whereHas('application.evaluation')->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function todaySessionsForInterviewer(User $interviewer): array
    {
        return RecruitmentInterviewSession::query()
            ->whereDate('session_date', today())
            ->whereHas('interviews', fn ($q) => $q->where('interviewer_id', $interviewer->id))
            ->with(['division:id,name', 'period:id,name'])
            ->withCount([
                'interviews as my_interviews_count' => fn ($q) => $q->where('interviewer_id', $interviewer->id),
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
        return RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->distinct()
            ->pluck('recruitment_interview_session_id')
            ->filter()
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

    private function formatTime(mixed $time): string
    {
        if (! is_string($time) || $time === '') {
            return '';
        }

        return strlen($time) >= 5 ? substr($time, 0, 5) : $time;
    }
}

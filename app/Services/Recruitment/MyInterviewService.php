<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class MyInterviewService
{
    public function __construct(
        private readonly InterviewerApplicationPresenter $presenter,
    ) {
    }

    public function paginateForInterviewer(User $interviewer, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->with([
                'application.primaryDivision',
                'application.secondaryDivision',
                'application.queueEntry',
                'application.evaluation',
                'session.division',
            ])
            ->orderBy('scheduled_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn (RecruitmentInterview $interview): array => $this->toListArray($interview));
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

        return [
            'interview_id' => $interview->id,
            'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
            'status' => $interview->status->value,
            'status_label' => $interview->status->label(),
            'location' => $interview->location,
            'room' => $interview->room,
            'application' => $application ? [
                'id' => $application->id,
                'full_name' => $application->full_name,
                'registration_number' => $application->registration_number,
                'nim' => $application->nim,
                'semester' => $application->semester,
                'primary_division' => $application->primaryDivision?->name,
            ] : null,
            'queue_number' => $application?->queueEntry?->queue_number,
            'has_evaluation' => $application?->evaluation !== null,
            'evaluation_locked' => $application?->evaluation?->isLocked() ?? false,
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
}

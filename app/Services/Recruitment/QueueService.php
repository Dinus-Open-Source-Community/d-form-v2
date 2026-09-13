<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Enums\Recruitment\QueueStatus;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentQueueEntry;
use Illuminate\Support\Facades\DB;
use LogicException;

final class QueueService
{
    public function __construct(
        private readonly EvaluationService $evaluationService,
    ) {
    }

    public function createFromAttendance(RecruitmentAttendance $attendance): RecruitmentQueueEntry
    {
        if (! $attendance->exists) {
            throw new LogicException('Attendance record must exist before creating a queue entry.');
        }

        $existing = RecruitmentQueueEntry::query()
            ->where('recruitment_application_id', $attendance->recruitment_application_id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($attendance): RecruitmentQueueEntry {
            $sessionId = $attendance->recruitment_interview_session_id;

            $nextNumber = (int) RecruitmentQueueEntry::query()
                ->where('recruitment_interview_session_id', $sessionId)
                ->lockForUpdate()
                ->max('queue_number') + 1;

            return RecruitmentQueueEntry::query()->create([
                'recruitment_application_id' => $attendance->recruitment_application_id,
                'recruitment_interview_session_id' => $sessionId,
                'queue_number' => max(1, $nextNumber),
                'status' => QueueStatus::Waiting,
            ]);
        });
    }

    public function callNext(RecruitmentInterviewSession $session): ?RecruitmentQueueEntry
    {
        return DB::transaction(function () use ($session): ?RecruitmentQueueEntry {
            $this->finalizeActiveEntries($session);

            $entry = RecruitmentQueueEntry::query()
                ->where('recruitment_interview_session_id', $session->id)
                ->where('status', QueueStatus::Waiting)
                ->orderBy('queue_number')
                ->lockForUpdate()
                ->first();

            if ($entry === null) {
                return null;
            }

            $entry->update([
                'status' => QueueStatus::Called,
                'called_at' => now(),
            ]);

            $entry->loadMissing('application.interview');
            $interview = $entry->application?->interview;

            if ($interview !== null) {
                $interview->update([
                    'status' => \App\Enums\Recruitment\InterviewStatus::Called,
                ]);
            }

            return $entry->fresh(['application']);
        });
    }

    public function complete(RecruitmentQueueEntry $entry): RecruitmentQueueEntry
    {
        $entry->loadMissing('application');

        if ($entry->application !== null) {
            $this->evaluationService->lockForApplication($entry->application);
        }

        $entry->update([
            'status' => QueueStatus::Completed,
            'completed_at' => now(),
        ]);

        $entry->loadMissing('application.interview');
        $interview = $entry->application?->interview;

        if ($interview !== null) {
            $interview->update([
                'status' => InterviewStatus::Completed,
            ]);
        }

        $this->advanceApplicationToFinalReview($entry->application);

        return $entry->fresh(['application']);
    }

    /**
     * @return array{
     *     entries: list<array<string, mixed>>,
     *     current: array<string, mixed>|null,
     *     next: array<string, mixed>|null,
     *     stats: array{waiting: int, called: int, completed: int, total: int}
     * }
     */
    public function snapshot(RecruitmentInterviewSession $session): array
    {
        $entries = RecruitmentQueueEntry::query()
            ->where('recruitment_interview_session_id', $session->id)
            ->with(['application:id,full_name,registration_number'])
            ->orderBy('queue_number')
            ->get();

        $current = $entries->first(fn (RecruitmentQueueEntry $entry): bool => in_array(
            $entry->status,
            [QueueStatus::Called, QueueStatus::InProgress],
            true,
        ));

        $next = $entries->first(fn (RecruitmentQueueEntry $entry): bool => $entry->status === QueueStatus::Waiting);

        return [
            'entries' => $entries->map(fn (RecruitmentQueueEntry $entry): array => $this->entryToArray($entry))->values()->all(),
            'current' => $current ? $this->entryToArray($current) : null,
            'next' => $next ? $this->entryToArray($next) : null,
            'stats' => [
                'waiting' => $entries->where('status', QueueStatus::Waiting)->count(),
                'called' => $entries->whereIn('status', [QueueStatus::Called, QueueStatus::InProgress])->count(),
                'completed' => $entries->where('status', QueueStatus::Completed)->count(),
                'total' => $entries->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entryToArray(RecruitmentQueueEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'queue_number' => $entry->queue_number,
            'status' => $entry->status->value,
            'status_label' => $entry->status->label(),
            'called_at' => $entry->called_at?->toIso8601String(),
            'completed_at' => $entry->completed_at?->toIso8601String(),
            'application' => $entry->application ? [
                'id' => $entry->application->id,
                'full_name' => $entry->application->full_name,
                'registration_number' => $entry->application->registration_number,
            ] : null,
        ];
    }

    private function finalizeActiveEntries(RecruitmentInterviewSession $session): void
    {
        $activeEntries = RecruitmentQueueEntry::query()
            ->where('recruitment_interview_session_id', $session->id)
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InProgress])
            ->with('application.interview')
            ->lockForUpdate()
            ->get();

        foreach ($activeEntries as $activeEntry) {
            $this->evaluationService->lockForApplication($activeEntry->application);

            $activeEntry->update([
                'status' => QueueStatus::Completed,
                'completed_at' => now(),
            ]);

            $interview = $activeEntry->application?->interview;

            if ($interview !== null) {
                $interview->update([
                    'status' => InterviewStatus::Completed,
                ]);
            }

            $this->advanceApplicationToFinalReview($activeEntry->application);
        }
    }

    private function advanceApplicationToFinalReview(?\App\Models\Recruitment\RecruitmentApplication $application): void
    {
        if ($application === null) {
            return;
        }

        if ($application->stage === ApplicationStage::Interview
            && $application->result === ApplicationResult::Pending) {
            $application->update(['stage' => ApplicationStage::FinalReview]);
        }
    }
}

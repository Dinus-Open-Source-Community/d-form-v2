<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EvaluationService
{
    public function __construct(
        private readonly RecruitmentActivityLogger $activityLogger,
        private readonly InterviewLifecycleService $interviewLifecycle,
    ) {
    }

    /**
     * @param  array{
     *     speaking_score: int,
     *     technical_score: int,
     *     attitude_score: int,
     *     recommendation: string,
     *     notes: string
     * }  $data
     */
    public function submit(
        User $actor,
        RecruitmentInterview $interview,
        array $data,
        bool $staffOverride = false,
        ?Request $request = null,
    ): RecruitmentEvaluation {
        $interview->loadMissing(['application', 'evaluation']);

        $application = $interview->application;

        if ($application === null) {
            throw ValidationException::withMessages([
                'interview' => ['Interview has no application.'],
            ]);
        }

        $existing = $interview->evaluation;

        if (! $staffOverride) {
            $this->assertNoEarlierPendingEvaluation($actor, $application);
        }

        if ($existing !== null && $existing->save_count >= RecruitmentEvaluation::MAX_SAVES) {
            throw ValidationException::withMessages([
                'evaluation' => ['Budget simpan penilaian sudah habis (3×). Penilaian terkunci permanen.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $application, $interview, $data, $existing, $staffOverride, $request): RecruitmentEvaluation {
            $saveCount = ($existing?->save_count ?? 0) + 1;

            $payload = [
                'speaking_score' => $data['speaking_score'],
                'technical_score' => $data['technical_score'],
                'attitude_score' => $data['attitude_score'],
                'recommendation' => EvaluationRecommendation::from($data['recommendation']),
                'notes' => $data['notes'] ?? null,
                'save_count' => $saveCount,
                'locked_at' => $saveCount >= RecruitmentEvaluation::MAX_SAVES ? now() : null,
                'evaluated_by' => $actor->id,
                'evaluated_at' => now(),
            ];

            if ($existing !== null) {
                $oldValues = $existing->only([
                    'speaking_score',
                    'technical_score',
                    'attitude_score',
                    'recommendation',
                    'notes',
                    'save_count',
                ]);

                $existing->update($payload);

                $this->activityLogger->log(
                    $staffOverride ? 'evaluation.staff_override' : 'evaluation.updated',
                    $actor,
                    $application,
                    $oldValues,
                    $existing->fresh()?->only([
                        'speaking_score',
                        'technical_score',
                        'attitude_score',
                        'recommendation',
                        'notes',
                        'save_count',
                    ]) ?? [],
                    'recruitment_evaluation',
                    $existing->id,
                    $request,
                );

                $this->advanceToFinalReview($application);

                return $existing->fresh();
            }

            $evaluation = RecruitmentEvaluation::query()->create([
                'recruitment_application_id' => $application->id,
                'recruitment_interview_id' => $interview->id,
                ...$payload,
            ]);

            $this->activityLogger->log(
                'evaluation.submitted',
                $actor,
                $application,
                [],
                $evaluation->only([
                    'speaking_score',
                    'technical_score',
                    'attitude_score',
                    'recommendation',
                    'notes',
                    'save_count',
                ]),
                'recruitment_evaluation',
                $evaluation->id,
                $request,
            );

            if ($interview->status !== InterviewStatus::Completed) {
                $this->interviewLifecycle->markCompleted($interview);
            }

            $this->advanceToFinalReview($application);

            return $evaluation;
        });
    }

    /**
     * Tolak bila ada applicant lain yang harus dinilai lebih dulu (tertua yang pending).
     */
    private function assertNoEarlierPendingEvaluation(User $actor, RecruitmentApplication $current): void
    {
        $blocker = $this->findBlockingPending($actor, $current);

        if ($blocker === null) {
            return;
        }

        throw ValidationException::withMessages([
            'evaluation' => ["Selesaikan penilaian applicant {$blocker->full_name} ({$blocker->registration_number}) terlebih dahulu."],
        ]);
    }

    /**
     * Cari applicant pending tertua milik interviewer selain yang sedang dinilai.
     */
    private function findBlockingPending(User $actor, RecruitmentApplication $current): ?RecruitmentApplication
    {
        $threshold = $current->interview?->scheduled_at ?? now();

        $interview = RecruitmentInterview::query()
            ->where('interviewer_id', $actor->id)
            ->where('recruitment_application_id', '!=', $current->id)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $threshold)
            ->whereHas('application', fn ($q) => $q->whereDoesntHave('evaluation'))
            ->with('application')
            ->orderBy('scheduled_at')
            ->first();

        return $interview?->application;
    }

    /**
     * Samakan guard QueueService: nilai masuk memajukan tahap Interview ke FinalReview.
     */
    private function advanceToFinalReview(RecruitmentApplication $application): void
    {
        if ($application->stage === ApplicationStage::Interview
            && $application->result === ApplicationResult::Pending) {
            $application->update(['stage' => ApplicationStage::FinalReview]);
        }
    }
}

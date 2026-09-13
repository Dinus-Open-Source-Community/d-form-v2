<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\EvaluationRecommendation;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EvaluationService
{
    public function __construct(
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    /**
     * @param  array{
     *     speaking_score: int,
     *     technical_score: int,
     *     attitude_score: int,
     *     recommendation: string,
     *     notes?: string|null
     * }  $data
     */
    public function submit(
        User $actor,
        RecruitmentApplication $application,
        array $data,
        bool $staffOverride = false,
        ?Request $request = null,
    ): RecruitmentEvaluation {
        $application->loadMissing(['interview', 'evaluation']);

        if ($application->interview === null) {
            throw ValidationException::withMessages([
                'interview' => ['Applicant has no scheduled interview.'],
            ]);
        }

        $existing = $application->evaluation;

        if ($existing !== null && $existing->isLocked() && ! $staffOverride) {
            throw new AuthorizationException('Evaluation is locked and cannot be edited.');
        }

        if ($existing !== null && $existing->isLocked() && $staffOverride) {
            return $this->updateLockedEvaluation($actor, $application, $existing, $data, $request);
        }

        return DB::transaction(function () use ($actor, $application, $data, $existing, $request): RecruitmentEvaluation {
            $payload = [
                'speaking_score' => $data['speaking_score'],
                'technical_score' => $data['technical_score'],
                'attitude_score' => $data['attitude_score'],
                'recommendation' => EvaluationRecommendation::from($data['recommendation']),
                'notes' => $data['notes'] ?? null,
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
                ]);

                $existing->update($payload);

                $this->activityLogger->log(
                    'evaluation.updated',
                    $actor,
                    $application,
                    $oldValues,
                    $existing->fresh()?->only([
                        'speaking_score',
                        'technical_score',
                        'attitude_score',
                        'recommendation',
                        'notes',
                    ]) ?? [],
                    'recruitment_evaluation',
                    $existing->id,
                    $request,
                );

                return $existing->fresh();
            }

            $evaluation = RecruitmentEvaluation::query()->create([
                'recruitment_application_id' => $application->id,
                'recruitment_interview_id' => $application->interview->id,
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
                ]),
                'recruitment_evaluation',
                $evaluation->id,
                $request,
            );

            return $evaluation;
        });
    }

    public function lockForApplication(?RecruitmentApplication $application): void
    {
        if ($application === null) {
            return;
        }

        $application->loadMissing('evaluation');

        $evaluation = $application->evaluation;

        if ($evaluation === null || $evaluation->isLocked()) {
            return;
        }

        $evaluation->update(['locked_at' => now()]);
    }

    /**
     * @param  array{
     *     speaking_score: int,
     *     technical_score: int,
     *     attitude_score: int,
     *     recommendation: string,
     *     notes?: string|null
     * }  $data
     */
    private function updateLockedEvaluation(
        User $actor,
        RecruitmentApplication $application,
        RecruitmentEvaluation $evaluation,
        array $data,
        ?Request $request,
    ): RecruitmentEvaluation {
        $oldValues = $evaluation->only([
            'speaking_score',
            'technical_score',
            'attitude_score',
            'recommendation',
            'notes',
            'locked_at',
        ]);

        $evaluation->update([
            'speaking_score' => $data['speaking_score'],
            'technical_score' => $data['technical_score'],
            'attitude_score' => $data['attitude_score'],
            'recommendation' => EvaluationRecommendation::from($data['recommendation']),
            'notes' => $data['notes'] ?? null,
            'evaluated_by' => $actor->id,
            'evaluated_at' => now(),
        ]);

        $this->activityLogger->log(
            'evaluation.staff_override',
            $actor,
            $application,
            $oldValues,
            $evaluation->fresh()?->only([
                'speaking_score',
                'technical_score',
                'attitude_score',
                'recommendation',
                'notes',
                'locked_at',
            ]) ?? [],
            'recruitment_evaluation',
            $evaluation->id,
            $request,
        );

        return $evaluation->fresh();
    }
}

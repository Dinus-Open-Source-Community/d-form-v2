<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Enums\Recruitment\CorrectionRequestStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentFinalDecision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentScreening;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class RecruitmentApplicationService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $query = RecruitmentApplication::query()
            ->with(['primaryDivision:id,name,code', 'secondaryDivision:id,name,code', 'period:id,name'])
            ->orderByDesc('submitted_at');

        if (! empty($filters['period_id'])) {
            $query->where('recruitment_period_id', $filters['period_id']);
        }

        if (! empty($filters['division_id'])) {
            $divisionId = $filters['division_id'];
            $query->where(function ($q) use ($divisionId): void {
                $q->where('primary_division_id', $divisionId)
                    ->orWhere('secondary_division_id', $divisionId);
            });
        }

        if (! empty($filters['stage'])) {
            $query->where('stage', $filters['stage']);
        }

        if (! empty($filters['queue'])) {
            $this->applyQueueFilter($query, (string) $filters['queue']);
        }

        if (! empty($filters['semester'])) {
            $query->where('semester', (int) $filters['semester']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array<string, int>
     */
    public function queueCounts(?string $periodId = null): array
    {
        $query = RecruitmentApplication::query();

        if ($periodId !== null && $periodId !== '') {
            $query->where('recruitment_period_id', $periodId);
        }

        $base = clone $query;

        return [
            'all' => (clone $base)->count(),
            'screening' => (clone $base)
                ->whereIn('stage', [ApplicationStage::Submitted, ApplicationStage::Screening])
                ->where('result', ApplicationResult::Pending)
                ->where('revision_required', false)
                ->count(),
            'revision' => (clone $base)->where('revision_required', true)->count(),
            'interview' => (clone $base)->where('stage', ApplicationStage::Interview)->count(),
            'final' => (clone $base)->where('stage', ApplicationStage::FinalReview)->count(),
            'done' => (clone $base)->where('stage', ApplicationStage::Completed)->count(),
            'corrections_pending' => RecruitmentCorrectionRequest::query()
                ->where('status', CorrectionRequestStatus::Pending)
                ->when($periodId, fn ($q) => $q->whereHas(
                    'application',
                    fn ($app) => $app->where('recruitment_period_id', $periodId),
                ))
                ->count(),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<RecruitmentApplication>  $query
     */
    private function applyQueueFilter($query, string $queue): void
    {
        match ($queue) {
            'screening' => $query
                ->whereIn('stage', [ApplicationStage::Submitted, ApplicationStage::Screening])
                ->where('result', ApplicationResult::Pending)
                ->where('revision_required', false),
            'revision' => $query->where('revision_required', true),
            'interview' => $query->where('stage', ApplicationStage::Interview),
            'final' => $query->where('stage', ApplicationStage::FinalReview),
            'done' => $query->where('stage', ApplicationStage::Completed),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(RecruitmentApplication $application): array
    {
        return [
            'id' => $application->id,
            'registration_number' => $application->registration_number,
            'full_name' => $application->full_name,
            'nim' => $application->nim,
            'semester' => $application->semester,
            'stage' => $application->stage->value,
            'stage_label' => $application->stage->label(),
            'result' => $application->result->value,
            'result_label' => $application->result->label(),
            'revision_required' => $application->revision_required,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'primary_division' => $application->primaryDivision ? [
                'id' => $application->primaryDivision->id,
                'name' => $application->primaryDivision->name,
            ] : null,
            'period' => $application->period ? [
                'id' => $application->period->id,
                'name' => $application->period->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toShowArray(RecruitmentApplication $application): array
    {
        $application->loadMissing([
            'period',
            'primaryDivision',
            'secondaryDivision',
            'document',
            'screenings.actor',
            'activityLogs.actor',
            'correctionRequests.reviewer',
            'evaluation.evaluator',
            'finalDecision.finalDivision',
            'finalDecision.decider',
        ]);

        return [
            'id' => $application->id,
            'registration_number' => $application->registration_number,
            'full_name' => $application->full_name,
            'nim' => $application->nim,
            'semester' => $application->semester,
            'phone' => $application->phone,
            'personal_email' => $application->personal_email,
            'student_email' => $application->student_email,
            'instagram_username' => $application->instagram_username,
            'stage' => $application->stage->value,
            'stage_label' => $application->stage->label(),
            'result' => $application->result->value,
            'result_label' => $application->result->label(),
            'is_verified' => $application->is_verified,
            'revision_required' => $application->revision_required,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'period' => $application->period ? [
                'id' => $application->period->id,
                'name' => $application->period->name,
            ] : null,
            'primary_division' => $this->divisionArray($application->primaryDivision),
            'secondary_division' => $this->divisionArray($application->secondaryDivision),
            'document' => $application->document ? [
                'cv_original_name' => $application->document->cv_original_name,
                'cv_mime' => $application->document->cv_mime,
                'cv_size_bytes' => $application->document->cv_size_bytes,
                'portfolio_type' => $application->document->portfolio_type,
                'portfolio_url' => $application->document->portfolio_url,
                'portfolio_original_name' => $application->document->portfolio_original_name,
                'portfolio_mime' => $application->document->portfolio_mime,
                'portfolio_size_bytes' => $application->document->portfolio_size_bytes,
                'has_cv_file' => filled($application->document->cv_path),
                'has_portfolio_file' => filled($application->document->portfolio_path),
            ] : null,
            'screenings' => $application->screenings
                ->sortByDesc('acted_at')
                ->values()
                ->map(fn (RecruitmentScreening $screening): array => [
                    'id' => $screening->id,
                    'decision' => $screening->decision->value,
                    'decision_label' => $screening->decision->label(),
                    'reason' => $screening->reason?->value,
                    'reason_label' => $screening->reason?->label(),
                    'notes' => $screening->notes,
                    'acted_at' => $screening->acted_at?->toIso8601String(),
                    'actor' => $screening->actor ? [
                        'id' => $screening->actor->id,
                        'name' => $screening->actor->name,
                    ] : null,
                ])
                ->all(),
            'activity_logs' => $application->activityLogs
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (RecruitmentActivityLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'actor' => $log->actor ? [
                        'id' => $log->actor->id,
                        'name' => $log->actor->name,
                    ] : null,
                ])
                ->all(),
            'correction_requests' => $application->correctionRequests
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (RecruitmentCorrectionRequest $correction): array => [
                    'id' => $correction->id,
                    'status' => $correction->status->value,
                    'status_label' => $correction->status->label(),
                    'request_message' => $correction->request_message,
                    'review_notes' => $correction->review_notes,
                    'reviewed_at' => $correction->reviewed_at?->toIso8601String(),
                    'completed_at' => $correction->completed_at?->toIso8601String(),
                    'reviewer' => $correction->reviewer ? [
                        'id' => $correction->reviewer->id,
                        'name' => $correction->reviewer->name,
                    ] : null,
                ])
                ->all(),
            'evaluation' => $this->evaluationArray($application->evaluation),
            'final_decision' => $this->finalDecisionArray($application->finalDecision),
            'can_screen' => $this->canScreen($application),
            'can_verify' => $this->canVerify($application),
            'can_decide_final' => $this->canDecideFinal($application),
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

    /**
     * @return list<array{id: string, name: string, code: string}>
     */
    public function divisionOptions(): array
    {
        return RecruitmentDivision::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'code'])
            ->map(fn (RecruitmentDivision $division): array => [
                'id' => $division->id,
                'name' => $division->name,
                'code' => $division->code,
            ])
            ->all();
    }

    private function canScreen(RecruitmentApplication $application): bool
    {
        if ($application->cancelled_at !== null) {
            return false;
        }

        if ($application->result !== \App\Enums\Recruitment\ApplicationResult::Pending) {
            return false;
        }

        return in_array($application->stage, [
            \App\Enums\Recruitment\ApplicationStage::Submitted,
            \App\Enums\Recruitment\ApplicationStage::Screening,
        ], true);
    }

    private function canVerify(RecruitmentApplication $application): bool
    {
        if ($application->cancelled_at !== null) {
            return false;
        }

        if ($application->is_verified) {
            return false;
        }

        if ($application->revision_required) {
            return false;
        }

        return $application->result === \App\Enums\Recruitment\ApplicationResult::Pending;
    }

    private function canDecideFinal(RecruitmentApplication $application): bool
    {
        if ($application->cancelled_at !== null) {
            return false;
        }

        if ($application->stage !== ApplicationStage::FinalReview) {
            return false;
        }

        return $application->result === ApplicationResult::Pending;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function evaluationArray(?RecruitmentEvaluation $evaluation): ?array
    {
        if ($evaluation === null) {
            return null;
        }

        return [
            'speaking_score' => $evaluation->speaking_score,
            'technical_score' => $evaluation->technical_score,
            'attitude_score' => $evaluation->attitude_score,
            'recommendation' => $evaluation->recommendation->value,
            'recommendation_label' => $evaluation->recommendation->label(),
            'notes' => $evaluation->notes,
            'is_locked' => $evaluation->isLocked(),
            'evaluated_at' => $evaluation->evaluated_at?->toIso8601String(),
            'evaluator' => $evaluation->evaluator ? [
                'id' => $evaluation->evaluator->id,
                'name' => $evaluation->evaluator->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function finalDecisionArray(?RecruitmentFinalDecision $decision): ?array
    {
        if ($decision === null) {
            return null;
        }

        return [
            'membership_type' => $decision->membership_type,
            'membership_type_label' => filled($decision->membership_type)
                ? \App\Enums\Recruitment\MembershipType::tryFrom($decision->membership_type)?->label()
                : null,
            'final_division' => $this->divisionArray($decision->finalDivision),
            'internal_reason' => $decision->internal_reason,
            'public_message' => $decision->public_message,
            'decided_at' => $decision->decided_at?->toIso8601String(),
            'decider' => $decision->decider ? [
                'id' => $decision->decider->id,
                'name' => $decision->decider->name,
            ] : null,
        ];
    }

    /**
     * @return array{id: string, name: string, code: string}|null
     */
    private function divisionArray(?RecruitmentDivision $division): ?array
    {
        if ($division === null) {
            return null;
        }

        return [
            'id' => $division->id,
            'name' => $division->name,
            'code' => $division->code,
        ];
    }
}

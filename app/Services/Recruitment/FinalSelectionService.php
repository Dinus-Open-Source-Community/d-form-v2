<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\MembershipType;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentFinalDecision;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinalSelectionService
{
    public function __construct(
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    public function accept(
        User $actor,
        RecruitmentApplication $application,
        MembershipType $membershipType,
        string $finalDivisionId,
        ?Request $request = null,
    ): RecruitmentFinalDecision {
        $this->assertCanDecide($application);

        $division = RecruitmentDivision::query()
            ->where('id', $finalDivisionId)
            ->where('is_active', true)
            ->first();

        if ($division === null) {
            throw ValidationException::withMessages([
                'final_division_id' => 'Divisi penempatan tidak valid.',
            ]);
        }

        return DB::transaction(function () use ($actor, $application, $membershipType, $finalDivisionId, $division, $request): RecruitmentFinalDecision {
            $oldStage = $application->stage;
            $oldResult = $application->result;

            $application->update([
                'stage' => ApplicationStage::Completed,
                'result' => ApplicationResult::Accepted,
            ]);

            $decision = RecruitmentFinalDecision::query()->updateOrCreate(
                ['recruitment_application_id' => $application->id],
                [
                    'membership_type' => $membershipType->value,
                    'final_division_id' => $finalDivisionId,
                    'internal_reason' => null,
                    'public_message' => null,
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                ],
            );

            $this->activityLogger->log(
                action: 'final.accept',
                actor: $actor,
                application: $application,
                oldValues: [
                    'stage' => $oldStage->value,
                    'result' => $oldResult->value,
                ],
                newValues: [
                    'stage' => ApplicationStage::Completed->value,
                    'result' => ApplicationResult::Accepted->value,
                    'membership_type' => $membershipType->value,
                    'final_division_id' => $finalDivisionId,
                    'final_division_name' => $division->name,
                ],
                entityType: 'recruitment_final_decision',
                entityId: $decision->id,
                request: $request,
            );

            SendRecruitmentNotificationJob::dispatch($application->id, 'final_accepted');

            return $decision;
        });
    }

    public function reject(
        User $actor,
        RecruitmentApplication $application,
        string $internalReason,
        string $publicMessage,
        ?Request $request = null,
    ): RecruitmentFinalDecision {
        $this->assertCanDecide($application);

        return DB::transaction(function () use ($actor, $application, $internalReason, $publicMessage, $request): RecruitmentFinalDecision {
            $oldStage = $application->stage;
            $oldResult = $application->result;

            $application->update([
                'stage' => ApplicationStage::Completed,
                'result' => ApplicationResult::Rejected,
            ]);

            $decision = RecruitmentFinalDecision::query()->updateOrCreate(
                ['recruitment_application_id' => $application->id],
                [
                    'membership_type' => null,
                    'final_division_id' => null,
                    'internal_reason' => $internalReason,
                    'public_message' => $publicMessage,
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                ],
            );

            $this->activityLogger->log(
                action: 'final.reject',
                actor: $actor,
                application: $application,
                oldValues: [
                    'stage' => $oldStage->value,
                    'result' => $oldResult->value,
                ],
                newValues: [
                    'stage' => ApplicationStage::Completed->value,
                    'result' => ApplicationResult::Rejected->value,
                ],
                entityType: 'recruitment_final_decision',
                entityId: $decision->id,
                request: $request,
            );

            SendRecruitmentNotificationJob::dispatch($application->id, 'final_rejected');

            return $decision;
        });
    }

    private function assertCanDecide(RecruitmentApplication $application): void
    {
        if ($application->cancelled_at !== null) {
            throw ValidationException::withMessages([
                'application' => 'Pendaftaran ini sudah dibatalkan.',
            ]);
        }

        if ($application->stage !== ApplicationStage::FinalReview) {
            throw ValidationException::withMessages([
                'application' => 'Pendaftaran tidak berada pada tahap final review.',
            ]);
        }

        if ($application->result !== ApplicationResult::Pending) {
            throw ValidationException::withMessages([
                'application' => 'Keputusan final sudah tidak dapat diubah.',
            ]);
        }
    }
}

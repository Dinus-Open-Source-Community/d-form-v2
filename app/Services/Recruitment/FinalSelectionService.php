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
        ?bool $includeGroupLink = null,
        ?string $newGroupUrl = null,
    ): RecruitmentFinalDecision {
        $this->assertCanDecide($application);

        $newGroupUrl = $newGroupUrl !== null ? trim($newGroupUrl) : null;
        if ($newGroupUrl === '') {
            $newGroupUrl = null;
        }

        if ($newGroupUrl !== null && ! str_starts_with($newGroupUrl, 'https://')) {
            throw ValidationException::withMessages([
                'whatsapp_group_url' => 'Link grup WA harus memakai https://.',
            ]);
        }

        $division = RecruitmentDivision::query()
            ->where('id', $finalDivisionId)
            ->where('is_active', true)
            ->first();

        if ($division === null) {
            throw ValidationException::withMessages([
                'final_division_id' => 'Divisi penempatan tidak valid.',
            ]);
        }

        return DB::transaction(function () use ($actor, $application, $membershipType, $finalDivisionId, $division, $request, $includeGroupLink, $newGroupUrl): RecruitmentFinalDecision {
            $oldStage = $application->stage;
            $oldResult = $application->result;

            $groupColumn = $membershipType === MembershipType::Aa ? 'whatsapp_group_aa_url' : 'whatsapp_group_member_url';

            $period = $application->period;
            if ($newGroupUrl !== null && $period !== null) {
                if (! $actor->can('update', $period)) {
                    throw ValidationException::withMessages([
                        'whatsapp_group_url' => 'Kamu tidak punya akses mengubah link grup periode ini. Minta admin mengisinya di tab Settings.',
                    ]);
                }
                $period->update([$groupColumn => $newGroupUrl]);
            }

            $resolvedUrl = $newGroupUrl ?? $period?->{$groupColumn};
            if ($includeGroupLink === false) {
                $resolvedUrl = null;
            }

            if ($includeGroupLink === true && trim((string) $resolvedUrl) === '') {
                throw ValidationException::withMessages([
                    'whatsapp_group_url' => 'Link grup WA wajib diisi bila menyertakan link grup di email.',
                ]);
            }

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
                    'is_cross_division' => $finalDivisionId !== $application->primary_division_id
                        && $finalDivisionId !== $application->secondary_division_id,
                    'placement_source' => 'cross_division_modal',
                ],
                entityType: 'recruitment_final_decision',
                entityId: $decision->id,
                request: $request,
            );

            SendRecruitmentNotificationJob::dispatch($application->id, 'final_accepted', null, null, null, $resolvedUrl !== null && trim((string) $resolvedUrl) !== '' ? (string) $resolvedUrl : null);

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

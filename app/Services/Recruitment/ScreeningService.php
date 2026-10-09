<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\ScreeningDecision;
use App\Enums\Recruitment\ScreeningReason;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentFinalDecision;
use App\Models\Recruitment\RecruitmentScreening;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ScreeningService
{
    public function __construct(
        private readonly RecruitmentActivityLogger $activityLogger,
        private readonly InterviewAutoEnrollService $autoEnroll,
    ) {
    }

    public function pass(User $actor, RecruitmentApplication $application, ?string $notes = null, ?Request $request = null, ?string $newGroupUrl = null, ?bool $includeGroupLink = null): RecruitmentScreening
    {
        $this->assertCanScreen($application);

        $newGroupUrl = $newGroupUrl !== null ? trim($newGroupUrl) : null;
        if ($newGroupUrl === '') {
            $newGroupUrl = null;
        }

        if ($newGroupUrl !== null && ! str_starts_with($newGroupUrl, 'https://')) {
            throw ValidationException::withMessages([
                'whatsapp_group_url' => 'Link grup WA harus memakai https://.',
            ]);
        }

        return DB::transaction(function () use ($actor, $application, $notes, $request, $newGroupUrl, $includeGroupLink): RecruitmentScreening {
            $period = $application->period;
            if ($newGroupUrl !== null && $period !== null) {
                if (! $actor->can('update', $period)) {
                    throw ValidationException::withMessages([
                        'whatsapp_group_url' => 'Kamu tidak punya akses mengubah link grup periode ini. Minta admin mengisinya di tab Settings.',
                    ]);
                }
                $period->update(['whatsapp_group_url' => $newGroupUrl]);
            }

            $resolvedUrl = $newGroupUrl ?? $period?->whatsapp_group_url;
            if ($includeGroupLink === false) {
                $resolvedUrl = null;
            }

            if ($includeGroupLink === true && trim((string) $resolvedUrl) === '') {
                throw ValidationException::withMessages([
                    'whatsapp_group_url' => 'Link grup WA wajib diisi bila menyertakan link grup di email.',
                ]);
            }
            $oldStage = $application->stage;
            $oldRevision = $application->revision_required;

            $application->update([
                'stage' => ApplicationStage::Interview,
                'revision_required' => false,
            ]);

            $screening = RecruitmentScreening::query()->create([
                'recruitment_application_id' => $application->id,
                'decision' => ScreeningDecision::Pass,
                'reason' => null,
                'notes' => $notes,
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            $this->activityLogger->log(
                action: 'screening.pass',
                actor: $actor,
                application: $application,
                oldValues: [
                    'stage' => $oldStage->value,
                    'revision_required' => $oldRevision,
                ],
                newValues: [
                    'stage' => ApplicationStage::Interview->value,
                    'revision_required' => false,
                ],
                entityType: 'recruitment_screening',
                entityId: $screening->id,
                request: $request,
            );

            SendRecruitmentNotificationJob::dispatch($application->id, 'passed_screening', null, null, null, $resolvedUrl !== null && trim((string) $resolvedUrl) !== '' ? (string) $resolvedUrl : null);

            $freshApplication = $application->fresh();
            $interview = $freshApplication !== null ? $this->autoEnroll->enroll($freshApplication) : null;

            if ($interview !== null) {
                SendRecruitmentNotificationJob::dispatch($application->id, 'interview_scheduled', $interview->id);
            }

            return $screening;
        });
    }

    public function requireRevision(
        User $actor,
        RecruitmentApplication $application,
        ScreeningReason $reason,
        ?string $notes = null,
        ?Request $request = null,
        ?array $sections = null,
    ): RecruitmentScreening {
        $this->assertCanScreen($application);

        return DB::transaction(function () use ($actor, $application, $reason, $notes, $request, $sections): RecruitmentScreening {
            $oldStage = $application->stage;
            $oldRevision = $application->revision_required;

            $application->update([
                'stage' => ApplicationStage::Screening,
                'revision_required' => true,
            ]);

            $screening = RecruitmentScreening::query()->create([
                'recruitment_application_id' => $application->id,
                'decision' => ScreeningDecision::RevisionRequired,
                'reason' => $reason,
                'notes' => $notes,
                'sections' => $sections,
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            $this->activityLogger->log(
                action: 'screening.revision_required',
                actor: $actor,
                application: $application,
                oldValues: [
                    'stage' => $oldStage->value,
                    'revision_required' => $oldRevision,
                ],
                newValues: [
                    'stage' => ApplicationStage::Screening->value,
                    'revision_required' => true,
                    'reason' => $reason->value,
                    'sections' => $sections,
                    'notes' => $notes,
                ],
                entityType: 'recruitment_screening',
                entityId: $screening->id,
                request: $request,
            );

            SendRecruitmentNotificationJob::dispatch($application->id, 'revision_required', null, $sections, $notes);

            return $screening;
        });
    }

    public function reject(
        User $actor,
        RecruitmentApplication $application,
        ScreeningReason $reason,
        ?string $notes = null,
        ?string $publicMessage = null,
        ?Request $request = null,
    ): RecruitmentScreening {
        $this->assertCanScreen($application);

        return DB::transaction(function () use ($actor, $application, $reason, $notes, $publicMessage, $request): RecruitmentScreening {
            $oldStage = $application->stage;
            $oldResult = $application->result;

            $application->update([
                'stage' => ApplicationStage::Completed,
                'result' => ApplicationResult::Rejected,
                'revision_required' => false,
            ]);

            $screening = RecruitmentScreening::query()->create([
                'recruitment_application_id' => $application->id,
                'decision' => ScreeningDecision::Reject,
                'reason' => $reason,
                'notes' => $notes,
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            RecruitmentFinalDecision::query()->updateOrCreate(
                ['recruitment_application_id' => $application->id],
                [
                    'membership_type' => null,
                    'final_division_id' => null,
                    'internal_reason' => $notes ?? $reason->label(),
                    'public_message' => $publicMessage ?? $reason->label(),
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                ],
            );

            $this->activityLogger->log(
                action: 'screening.reject',
                actor: $actor,
                application: $application,
                oldValues: [
                    'stage' => $oldStage->value,
                    'result' => $oldResult->value,
                ],
                newValues: [
                    'stage' => ApplicationStage::Completed->value,
                    'result' => ApplicationResult::Rejected->value,
                    'reason' => $reason->value,
                ],
                entityType: 'recruitment_screening',
                entityId: $screening->id,
                request: $request,
            );

            SendRecruitmentNotificationJob::dispatch($application->id, 'rejected_screening');

            return $screening;
        });
    }

    private function assertCanScreen(RecruitmentApplication $application): void
    {
        if ($application->cancelled_at !== null) {
            throw ValidationException::withMessages([
                'application' => 'Pendaftaran ini sudah dibatalkan.',
            ]);
        }

        if (! in_array($application->stage, [ApplicationStage::Submitted, ApplicationStage::Screening], true)) {
            throw ValidationException::withMessages([
                'application' => 'Pendaftaran tidak berada pada tahap screening.',
            ]);
        }

        if ($application->result !== ApplicationResult::Pending) {
            throw ValidationException::withMessages([
                'application' => 'Keputusan screening sudah tidak dapat diubah.',
            ]);
        }
    }
}

<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\CorrectionRequestStatus;
use App\Jobs\Recruitment\SendRecruitmentCorrectionRequestStaffJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CorrectionRequestService
{
    public function __construct(
        private readonly ApplicationEditGate $editGate,
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    public function store(RecruitmentApplication $application, string $message, ?Request $request = null): RecruitmentCorrectionRequest
    {
        if (! $this->editGate->canRequestCorrection($application)) {
            throw ValidationException::withMessages([
                'request_message' => 'Permintaan koreksi tidak dapat dibuat saat ini.',
            ]);
        }

        return DB::transaction(function () use ($application, $message, $request): RecruitmentCorrectionRequest {
            $correction = RecruitmentCorrectionRequest::query()->create([
                'recruitment_application_id' => $application->id,
                'status' => CorrectionRequestStatus::Pending,
                'request_message' => $message,
            ]);

            $this->activityLogger->logApplicant(
                action: 'correction.requested',
                application: $application,
                newValues: [
                    'correction_request_id' => $correction->id,
                    'request_message' => $message,
                ],
                entityType: 'recruitment_correction_request',
                entityId: $correction->id,
                request: $request,
            );

            SendRecruitmentCorrectionRequestStaffJob::dispatch($correction->id);

            return $correction;
        });
    }

    public function approve(
        User $staff,
        RecruitmentCorrectionRequest $correction,
        ?string $reviewNotes = null,
        ?Request $request = null,
    ): RecruitmentCorrectionRequest {
        $this->assertPending($correction);

        return DB::transaction(function () use ($staff, $correction, $reviewNotes, $request): RecruitmentCorrectionRequest {
            $correction->update([
                'status' => CorrectionRequestStatus::Approved,
                'review_notes' => $reviewNotes,
                'reviewed_by' => $staff->id,
                'reviewed_at' => now(),
            ]);

            $application = $correction->application;

            $this->activityLogger->log(
                action: 'correction.approved',
                actor: $staff,
                application: $application,
                newValues: [
                    'correction_request_id' => $correction->id,
                    'review_notes' => $reviewNotes,
                ],
                entityType: 'recruitment_correction_request',
                entityId: $correction->id,
                request: $request,
            );

            return $correction->fresh();
        });
    }

    public function reject(
        User $staff,
        RecruitmentCorrectionRequest $correction,
        ?string $reviewNotes = null,
        ?Request $request = null,
    ): RecruitmentCorrectionRequest {
        $this->assertPending($correction);

        return DB::transaction(function () use ($staff, $correction, $reviewNotes, $request): RecruitmentCorrectionRequest {
            $correction->update([
                'status' => CorrectionRequestStatus::Rejected,
                'review_notes' => $reviewNotes,
                'reviewed_by' => $staff->id,
                'reviewed_at' => now(),
            ]);

            $application = $correction->application;

            $this->activityLogger->log(
                action: 'correction.rejected',
                actor: $staff,
                application: $application,
                newValues: [
                    'correction_request_id' => $correction->id,
                    'review_notes' => $reviewNotes,
                ],
                entityType: 'recruitment_correction_request',
                entityId: $correction->id,
                request: $request,
            );

            return $correction->fresh();
        });
    }

    private function assertPending(RecruitmentCorrectionRequest $correction): void
    {
        if ($correction->status !== CorrectionRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'correction' => 'Permintaan koreksi sudah diproses.',
            ]);
        }
    }
}

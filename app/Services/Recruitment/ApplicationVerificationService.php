<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApplicationVerificationService
{
    public function __construct(
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    public function verify(User $staff, RecruitmentApplication $application, ?Request $request = null): RecruitmentApplication
    {
        if ($application->is_verified) {
            throw ValidationException::withMessages([
                'application' => 'Pendaftaran sudah diverifikasi.',
            ]);
        }

        if ($application->revision_required) {
            throw ValidationException::withMessages([
                'application' => 'Pendaftaran masih menunggu revisi applicant.',
            ]);
        }

        return DB::transaction(function () use ($staff, $application, $request): RecruitmentApplication {
            $application->update([
                'is_verified' => true,
                'verified_at' => now(),
                'verified_by' => $staff->id,
            ]);

            $this->activityLogger->log(
                action: 'application.verified',
                actor: $staff,
                application: $application,
                newValues: [
                    'is_verified' => true,
                    'verified_by' => $staff->id,
                ],
                entityType: 'recruitment_application',
                entityId: $application->id,
                request: $request,
            );

            return $application->fresh();
        });
    }
}

<?php

namespace App\Services\Recruitment;

use App\Jobs\Recruitment\SendRecruitmentApplicationConfirmationJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class RecruitmentTrackingResendService
{
    public function __construct(
        private readonly RecruitmentTrackingTokenGenerator $trackingTokenGenerator,
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    public function resend(User $actor, RecruitmentApplication $application, ?Request $request = null): void
    {
        if ($application->cancelled_at !== null) {
            throw ValidationException::withMessages([
                'application' => ['Pendaftaran ini sudah dibatalkan.'],
            ]);
        }

        if (blank($application->personal_email)) {
            throw ValidationException::withMessages([
                'application' => ['Applicant tidak memiliki email pribadi.'],
            ]);
        }

        $trackingToken = $this->trackingTokenGenerator->generate();

        DB::transaction(function () use ($application, $trackingToken, $actor, $request): void {
            $application->update([
                'tracking_token_hash' => Hash::make($trackingToken),
            ]);

            $this->activityLogger->log(
                action: 'tracking.resend',
                actor: $actor,
                application: $application,
                newValues: [
                    'recipient_email' => $application->personal_email,
                ],
                request: $request,
            );
        });

        SendRecruitmentApplicationConfirmationJob::dispatch(
            $application->id,
            $trackingToken,
        );
    }
}

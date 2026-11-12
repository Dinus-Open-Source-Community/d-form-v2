<?php

namespace App\Services\Recruitment;

use App\Jobs\Recruitment\SendRecruitmentApplicationConfirmationJob;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class RecruitmentTrackingResendService
{
    public function __construct(
        private readonly RecruitmentTrackingTokenGenerator $trackingTokenGenerator,
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    /** Queue a new tracking link without invalidating the current token before delivery succeeds. */
    public function resend(User $actor, RecruitmentApplication $application, ?Request $request = null): void
    {
        $this->assertApplicationCanResend($application);
        $this->queueConfirmation($actor, $application, $request, 'tracking');
    }

    /** Queue a generic confirmation resend without activating its token before delivery. */
    public function resendConfirmation(User $actor, RecruitmentApplication $application, ?Request $request = null): void
    {
        $this->queueConfirmation($actor, $application, $request, 'confirmation');
    }

    /** Reserve the resend slot and generation together so concurrent requests have a stable order. */
    private function queueConfirmation(
        User $actor,
        RecruitmentApplication $application,
        ?Request $request,
        string $type,
    ): void {
        $trackingToken = $this->trackingTokenGenerator->generate();

        $generationReservation = DB::transaction(function () use ($application, $actor, $request, $type): array {
            $lockedApplication = RecruitmentApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->throwUnlessUnderDailyLimit($lockedApplication, $type);

            $previousGeneration = $lockedApplication->tracking_token_generation === null
                ? null
                : (int) $lockedApplication->tracking_token_generation;
            $generation = ($previousGeneration ?? 0) + 1;

            $lockedApplication->forceFill([
                'tracking_token_generation' => $generation,
            ])->save();

            if ($type === 'tracking') {
                $this->activityLogger->log(
                    action: 'tracking.resend',
                    actor: $actor,
                    application: $lockedApplication,
                    newValues: ['type' => 'tracking'],
                    request: $request,
                );
            }

            $this->activityLogger->log(
                action: 'email.resend',
                actor: $actor,
                application: $lockedApplication,
                newValues: ['type' => $type],
                request: $request,
            );

            return [
                'generation' => $generation,
                'previous_generation' => $previousGeneration,
            ];
        });

        try {
            SendRecruitmentApplicationConfirmationJob::dispatch(
                $application->id,
                $trackingToken,
                $generationReservation['generation'],
            );
        } catch (Throwable $exception) {
            DB::transaction(function () use ($application, $generationReservation): void {
                $lockedApplication = RecruitmentApplication::query()
                    ->whereKey($application->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($lockedApplication === null
                    || (int) $lockedApplication->tracking_token_generation !== $generationReservation['generation']) {
                    return;
                }

                $lockedApplication->forceFill([
                    'tracking_token_generation' => $generationReservation['previous_generation'],
                ])->save();
            });

            throw $exception;
        }
    }

    private function assertApplicationCanResend(RecruitmentApplication $application): void
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
    }

    /** The caller holds the application row lock while checking and recording the resend. */
    private function throwUnlessUnderDailyLimit(RecruitmentApplication $application, string $type): void
    {
        $query = RecruitmentActivityLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('created_at', '>=', now()->subDay());

        if ($type === 'tracking') {
            $query->where('action', 'tracking.resend');
        } else {
            $query->where('action', 'email.resend')
                ->where('new_values->type', $type);
        }

        if ($query->count() >= RecruitmentEmailResendService::MAX_PER_DAY) {
            abort(429, RecruitmentEmailResendService::DAILY_LIMIT_MESSAGE);
        }
    }
}

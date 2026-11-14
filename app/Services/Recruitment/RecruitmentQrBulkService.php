<?php

namespace App\Services\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentQrDispatchOutbox;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecruitmentQrBulkService
{
    /** Inject the QR eligibility, activity audit, and durable dispatch seams. */
    public function __construct(
        private readonly RecruitmentActivityLogger $activityLogger,
        private readonly RecruitmentQrEligibilityQuery $eligibilityQuery,
        private readonly RecruitmentQrDispatchRecovery $dispatchRecovery,
    ) {
    }

    /**
     * Enqueue each reserved QR immediately; this request path does not depend on a scheduler or recovery poll.
     * @return array{scheduled:int,skipped:int}
     */
    public function send(User $actor, RecruitmentPeriod $period, bool $includeSent = false): array
    {
        $recipientsQuery = $includeSent
            ? $this->eligibilityQuery->resendable($period)
            : $this->eligibilityQuery->eligible($period);

        $recipients = $recipientsQuery
            ->orderBy('submitted_at')
            ->get();

        $skipped = $includeSent ? 0 : $this->eligibilityQuery->countInFlight($period);

        $delaySeconds = max(0, (int) config('registration.email_send_delay_seconds', 7));

        // No scheduler runs in this repo, so a bulk request also drains the recovery net: pending
        // intents and lost `queued` jobs from earlier requests. Bounded by the batch/horizon and
        // guarded by a cache lock, so this cannot flood the queue.
        $this->dispatchRecovery->recoverOpportunistically();

        $anchor = $this->dispatchRecovery->spacingAnchor();
        $initialSlot = null;

        $scheduled = 0;

        foreach ($recipients as $index => $application) {
            /** @var array{application: RecruitmentApplication, emailLog: EmailLog, outbox: RecruitmentQrDispatchOutbox}|null $reservation */
            $reservation = DB::transaction(function () use ($application, $includeSent, $period, $index, $delaySeconds): ?array {
                // Serialize competing QR claims on the application row, then recheck after acquiring the lock.
                $lockedApplication = RecruitmentApplication::query()
                    ->whereKey($application->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedApplication === null) {
                    return null;
                }

                $stillEligible = ($includeSent
                    ? $this->eligibilityQuery->resendable($period)
                    : $this->eligibilityQuery->eligible($period))
                    ->whereKey($lockedApplication->id)
                    ->exists();

                if (! $stillEligible) {
                    return null;
                }

                $emailLog = EmailLog::query()->create([
                    'recruitment_application_id' => $lockedApplication->id,
                    'event_id' => null,
                    'user_id' => null,
                    'recipient_email' => (string) $lockedApplication->personal_email,
                    'status' => EmailLogStatus::Queued,
                    'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
                    'error_message' => null,
                    'sent_at' => null,
                ]);

                $outbox = RecruitmentQrDispatchOutbox::query()->create([
                    'email_log_id' => $emailLog->id,
                    'status' => RecruitmentQrDispatchOutbox::STATUS_PENDING,
                    'available_at' => now()->addSeconds($index * $delaySeconds),
                    'delay_seconds' => $delaySeconds,
                    'dispatch_attempts' => 0,
                ]);

                return [
                    'application' => $lockedApplication,
                    'emailLog' => $emailLog,
                    'outbox' => $outbox,
                ];
            });

            if ($reservation === null) {
                continue;
            }

            $reservedApplication = $reservation['application'];
            $outbox = $reservation['outbox'];

            // Enqueue right after the reservation commits, outside its transaction: the request
            // path must not depend on the recovery poll, which no scheduler drives in this repo.
            // The first issued slot is reserved under a cache lock so concurrent bulk sends in
            // other periods cannot start on the same slot.
            if ($initialSlot === null) {
                $initialSlot = $this->dispatchRecovery->reserveInitialSlot($outbox->delay_seconds, $anchor);
                $slot = $this->dispatchRecovery->nextSlot(
                    reservedAt: $outbox->available_at,
                    delaySeconds: $outbox->delay_seconds,
                    anchor: ['at' => $initialSlot, 'delay_seconds' => 0],
                );
            } else {
                $slot = $this->dispatchRecovery->nextSlot(
                    reservedAt: $outbox->available_at,
                    delaySeconds: $outbox->delay_seconds,
                    anchor: $anchor,
                );
            }

            try {
                $this->dispatchRecovery->enqueueOutbox(
                    outbox: $outbox,
                    applicationId: (string) $reservedApplication->id,
                    slot: $slot,
                );
                $anchor = ['at' => $slot, 'delay_seconds' => $outbox->delay_seconds];
            } catch (Throwable $exception) {
                // The intent stays pending for the recovery poll; one bad push must not abort
                // the remaining reservations of an approved bulk send.
                Log::warning('recruitment_qr_outbox_enqueue_failed', [
                    'email_log_id' => $outbox->email_log_id,
                    'exception_class' => $exception::class,
                ]);
            }

            $this->activityLogger->log(
                action: 'email.qr_bulk',
                actor: $actor,
                application: $reservedApplication,
                newValues: [
                    'template' => 'interview_scheduled',
                    'recipient_email' => (string) $reservedApplication->personal_email,
                ],
            );

            $scheduled++;
        }

        // Honest scope: this retries only intents of *this* request whose slot is inside the
        // recovery horizon. A failed push whose slot is already further out (a large batch) stays
        // `pending` until `recruitment:dispatch-qr-outbox` or an opportunistic page-load recovery
        // runs; it is never silently dropped.
        try {
            $this->dispatchRecovery->dispatchPending();
        } catch (Throwable $exception) {
            Log::warning('recruitment_qr_outbox_recovery_failed', [
                'exception_class' => $exception::class,
            ]);
        }

        return ['scheduled' => $scheduled, 'skipped' => $skipped];
    }
}

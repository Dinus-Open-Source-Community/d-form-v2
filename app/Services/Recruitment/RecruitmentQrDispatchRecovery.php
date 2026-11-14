<?php

namespace App\Services\Recruitment;

use App\Enums\EmailLogStatus;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\Recruitment\RecruitmentQrDispatchOutbox;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Durable QR dispatch with bounded recovery; stale `sending` rows are ambiguous and require operator reconciliation, never auto-resend. */
final class RecruitmentQrDispatchRecovery
{
    // Bound locks per poll so a recovery run cannot flood the queue with a large backlog.
    private const DEFAULT_BATCH_SIZE = 100;

    /** Grace before a `sending` intent is reported as stuck (no automatic resend). */
    private const DEFAULT_STALE_SENDING_SECONDS = 600;

    /** Grace before a `queued` intent is treated as a lost job and re-queued. */
    private const DEFAULT_STALE_QUEUED_SECONDS = 900;

    /** Cross-process send-slot pointer; the lock protects read-modify-write of the pointer. */
    private const SLOT_LOCK_KEY = 'recruitment-qr-slot-lock';

    private const SLOT_POINTER_KEY = 'recruitment-qr-slot-pointer';

    /** Guards the opportunistic recovery so page loads cannot poll concurrently. */
    private const RECOVERY_LOCK_KEY = 'recruitment-qr-recovery';

    /**
     *
     * Anchor of the most recently occupied send slot: the latest queued/sending intent, or the newest completed send when it is more recent.
     * @return array{at: ?CarbonInterface, delay_seconds: ?int}
     *
     */
    public function spacingAnchor(): array
    {
        $anchor = ['at' => null, 'delay_seconds' => null];

        foreach ([
            RecruitmentQrDispatchOutbox::STATUS_QUEUED,
            RecruitmentQrDispatchOutbox::STATUS_SENDING,
        ] as $status) {
            $lastInFlight = RecruitmentQrDispatchOutbox::query()
                ->where('status', $status)
                ->orderByDesc('available_at')
                ->first(['available_at', 'delay_seconds']);

            if ($lastInFlight !== null && ($anchor['at'] === null || $lastInFlight->available_at->greaterThan($anchor['at']))) {
                $anchor = ['at' => $lastInFlight->available_at, 'delay_seconds' => $lastInFlight->delay_seconds];
            }
        }

        $lastSent = RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_SENT)
            ->whereNotNull('sent_at')
            ->orderByDesc('sent_at')
            ->first(['sent_at', 'delay_seconds']);
        $lastFailed = RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_FAILED)
            ->whereNotNull('failed_at')
            ->orderByDesc('failed_at')
            ->first(['failed_at', 'delay_seconds']);

        if ($lastSent !== null && ($anchor['at'] === null || $lastSent->sent_at->greaterThan($anchor['at']))) {
            $anchor = ['at' => $lastSent->sent_at, 'delay_seconds' => $lastSent->delay_seconds];
        }

        if ($lastFailed !== null && ($anchor['at'] === null || $lastFailed->failed_at->greaterThan($anchor['at']))) {
            $anchor = ['at' => $lastFailed->failed_at, 'delay_seconds' => $lastFailed->delay_seconds];
        }

        return $anchor;
    }

    /**
     *
     * Next slot for an intent reserved at `$reservedAt`: never in the past, and at least
     * `$delaySeconds` after the last occupied slot.
     *
     * @param  array{at: ?CarbonInterface, delay_seconds: ?int}  $anchor
     *
     */
    public function nextSlot(CarbonInterface $reservedAt, int $delaySeconds, array $anchor): CarbonInterface
    {
        $now = now();
        // Normalize the timezone: a slot created from a cache timestamp must still be persisted
        // in the application timezone, otherwise Eloquent writes UTC wall-clock time and the row
        // reads back shifted by the UTC offset.
        $slot = $reservedAt->copy()->setTimezone($now->getTimezone());

        if ($slot->lessThan($now)) {
            $slot = $now->copy();
        }

        if ($anchor['at'] !== null && $anchor['delay_seconds'] !== null) {
            $minimumSchedule = $anchor['at']->copy()->addSeconds($anchor['delay_seconds']);

            if ($slot->lessThan($minimumSchedule)) {
                $slot = $minimumSchedule;
            }
        }

        return $slot;
    }

    /** Reserve the first send slot under a shared lock; spacing remains consistent across bulk sends and retries.
     * @param  array{at: ?CarbonInterface, delay_seconds: ?int}  $anchor
     */
    public function reserveInitialSlot(int $delaySeconds, array $anchor): CarbonInterface
    {
        try {
            return Cache::lock(self::SLOT_LOCK_KEY, 5)->block(
                2,
                fn (): CarbonInterface => $this->issueInitialSlot($delaySeconds, $anchor),
            );
        } catch (LockTimeoutException $exception) {
            Log::warning('recruitment_qr_slot_lock_timeout', [
                'exception_class' => $exception::class,
            ]);

            return $this->nextSlot(now(), $delaySeconds, $anchor);
        }
    }

    /** Claim a pending intent before queue push; a push failure returns it to pending for bounded recovery.
     * @throws Throwable when the queue push fails
     */
    public function enqueueOutbox(
        RecruitmentQrDispatchOutbox $outbox,
        string $applicationId,
        CarbonInterface $slot,
    ): void {
        $claimed = RecruitmentQrDispatchOutbox::query()
            ->whereKey($outbox->email_log_id)
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_PENDING)
            ->update([
                'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
                'available_at' => $slot,
                'dispatch_attempts' => DB::raw('dispatch_attempts + 1'),
            ]);

        if ($claimed !== 1) {
            return;
        }

        try {
            $this->pushJob((string) $outbox->email_log_id, $applicationId, $slot);
        } catch (Throwable $exception) {
            RecruitmentQrDispatchOutbox::query()
                ->whereKey($outbox->email_log_id)
                ->where('status', RecruitmentQrDispatchOutbox::STATUS_QUEUED)
                ->update(['status' => RecruitmentQrDispatchOutbox::STATUS_PENDING]);

            throw $exception;
        }
    }

    /** Recovery poll for intents left `pending` (queue push failed, request crashed mid-batch). */
    public function dispatchPending(int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $batchSize = min(self::DEFAULT_BATCH_SIZE, max(1, $batchSize));

        return DB::transaction(function () use ($batchSize): int {
            $now = now();
            $dispatchHorizonEnd = $now->copy()->addSeconds(
                (int) config('recruitment.qr_dispatch_horizon_seconds'),
            );
            $anchor = $this->spacingAnchor();
            $initialSlot = null;

            $outboxes = RecruitmentQrDispatchOutbox::query()
                ->with('emailLog:id,recruitment_application_id')
                ->where('status', RecruitmentQrDispatchOutbox::STATUS_PENDING)
                ->where('available_at', '<=', $now)
                // `status` + `available_at` is enough for the composite index to satisfy both the
                // filter and the order (`Using index`, no filesort). Extra tiebreakers forced a
                // filesort over the whole match set; ties are irrelevant because the claim below
                // is idempotent and each claimed row leaves `pending`.
                ->orderBy('available_at')
                ->limit($batchSize)
                ->lockForUpdate()
                ->get();

            $dispatched = 0;

            foreach ($outboxes as $outbox) {
                $scheduledAt = $this->nextSlot($outbox->available_at, $outbox->delay_seconds, $anchor);

                if ($scheduledAt->greaterThan($dispatchHorizonEnd)) {
                    break;
                }

                if ($initialSlot === null) {
                    $initialSlot = $this->reserveInitialSlot($outbox->delay_seconds, $anchor);
                    $scheduledAt = $this->nextSlot(
                        $outbox->available_at,
                        $outbox->delay_seconds,
                        ['at' => $initialSlot, 'delay_seconds' => 0],
                    );

                    if ($scheduledAt->greaterThan($dispatchHorizonEnd)) {
                        break;
                    }
                }

                $applicationId = $outbox->emailLog?->recruitment_application_id;

                if ($applicationId === null) {
                    Log::warning('recruitment_qr_outbox_application_missing', [
                        'email_log_id' => $outbox->email_log_id,
                    ]);

                    $outbox->forceFill([
                        'status' => RecruitmentQrDispatchOutbox::STATUS_FAILED,
                        'failed_at' => $now,
                    ])->save();

                    $outbox->emailLog?->forceFill([
                        'status' => EmailLogStatus::Failed,
                        'error_message' => 'QR notification could not be delivered. Please contact support.',
                        'sent_at' => null,
                    ])->save();

                    continue;
                }

                $this->enqueueOutbox($outbox, (string) $applicationId, $scheduledAt);

                $anchor = ['at' => $scheduledAt, 'delay_seconds' => $outbox->delay_seconds];
                $dispatched++;
            }

            return $dispatched;
        });
    }

    /** Re-queue stale queued intents whose jobs were lost; claim updates prevent duplicates. */
    public function repushStaleQueued(int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $batchSize = min(self::DEFAULT_BATCH_SIZE, max(1, $batchSize));
        $staleBefore = now()->subSeconds((int) config('recruitment.qr_queued_stale_seconds'));

        return DB::transaction(function () use ($batchSize, $staleBefore): int {
            $outboxes = RecruitmentQrDispatchOutbox::query()
                ->with('emailLog:id,recruitment_application_id')
                ->where('status', RecruitmentQrDispatchOutbox::STATUS_QUEUED)
                ->where('available_at', '<=', $staleBefore)
                ->orderBy('available_at')
                ->limit($batchSize)
                ->lockForUpdate()
                ->get();

            $requeued = 0;

            foreach ($outboxes as $outbox) {
                $applicationId = $outbox->emailLog?->recruitment_application_id;

                if ($applicationId === null) {
                    continue;
                }

                $now = now();
                $leased = RecruitmentQrDispatchOutbox::query()
                    ->whereKey($outbox->email_log_id)
                    ->where('status', RecruitmentQrDispatchOutbox::STATUS_QUEUED)
                    ->where('available_at', '<=', $staleBefore)
                    ->update([
                        'available_at' => $now,
                        'dispatch_attempts' => DB::raw('dispatch_attempts + 1'),
                    ]);

                if ($leased !== 1) {
                    continue;
                }

                try {
                    $this->pushJob((string) $outbox->email_log_id, (string) $applicationId, $now);
                } catch (Throwable $exception) {
                    // The lease stays advanced: retry after the grace window instead of hot-looping.
                    Log::warning('recruitment_qr_outbox_repush_failed', [
                        'email_log_id' => $outbox->email_log_id,
                        'exception_class' => $exception::class,
                    ]);

                    continue;
                }

                $requeued++;
            }

            return $requeued;
        });
    }

    /** Recover due QR intents from page loads because no scheduler runs in this deployment. */
    public function recoverOpportunistically(): int
    {
        $lock = Cache::lock(self::RECOVERY_LOCK_KEY, 60);

        if (! $lock->get()) {
            return 0;
        }

        try {
            return $this->dispatchPending() + $this->repushStaleQueued();
        } catch (Throwable $exception) {
            Log::warning('recruitment_qr_opportunistic_recovery_failed', [
                'exception_class' => $exception::class,
            ]);

            return 0;
        } finally {
            $lock->release();
        }
    }

    /** Count stale `sending` intents for operator reconciliation; never auto-resend them. */
    public function staleSendingCount(): int
    {
        $threshold = now()->subSeconds(
            (int) config('recruitment.qr_sending_stale_seconds', self::DEFAULT_STALE_SENDING_SECONDS),
        );

        return RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_SENDING)
            ->where('updated_at', '<=', $threshold)
            ->count();
    }

    /** Count queued intents whose scheduled slot is stale. */
    public function staleQueuedCount(): int
    {
        $threshold = now()->subSeconds(
            (int) config('recruitment.qr_queued_stale_seconds', self::DEFAULT_STALE_QUEUED_SECONDS),
        );

        return RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_QUEUED)
            ->where('available_at', '<=', $threshold)
            ->count();
    }

    /** Push the delayed job for one claimed intent; the delivery key keeps retries idempotent. */
    private function pushJob(string $emailLogId, string $applicationId, CarbonInterface $slot): void
    {
        SendRecruitmentNotificationJob::dispatch(
            applicationId: $applicationId,
            templateKey: 'interview_scheduled',
            qrEmailLogId: $emailLogId,
        )->delay($slot);
    }

    /**
     *
     * Read-modify-write of the shared slot pointer; must run inside the slot lock.
     *
     * @param  array{at: ?CarbonInterface, delay_seconds: ?int}  $anchor
     *
     */
    private function issueInitialSlot(int $delaySeconds, array $anchor): CarbonInterface
    {
        $pointerAt = Cache::get(self::SLOT_POINTER_KEY);
        $floor = $anchor;

        if (is_int($pointerAt) && ($floor['at'] === null || $pointerAt > $floor['at']->timestamp)) {
            $floor = ['at' => now()->setTimestamp($pointerAt), 'delay_seconds' => $delaySeconds];
        }

        $slot = $this->nextSlot(now(), $delaySeconds, $floor);

        Cache::put(self::SLOT_POINTER_KEY, $slot->timestamp, now()->addDay());

        return $slot;
    }
}

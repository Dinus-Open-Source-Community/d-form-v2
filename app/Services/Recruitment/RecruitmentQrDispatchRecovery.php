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

/**
 * Durable QR dispatch: queueing one intent at a time, a bounded recovery poll, and safe
 * re-queueing of intents whose queue job was lost.
 *
 * The request path calls {@see enqueueOutbox()} for every intent it reserves, so delivery never
 * depends on a scheduler being present (this repo runs none). {@see dispatchPending()} recovers
 * intents that stayed `pending`; {@see repushStaleQueued()} recovers `queued` intents whose job
 * disappeared without invoking `failed()`. In every path the outbox claim is authoritative, so a
 * second job for the same intent cannot produce a second email.
 */
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
     * Anchor of the most recently occupied send slot: the latest queued/sending intent, or the
     * newest completed send when it is more recent. Read as two single-status index scans
     * (`status` + `available_at`) instead of one `IN` scan, which would filesort.
     *
     * @return array{at: ?CarbonInterface, delay_seconds: ?int}
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
     * Next slot for an intent reserved at `$reservedAt`: never in the past, and at least
     * `$delaySeconds` after the last occupied slot.
     *
     * @param  array{at: ?CarbonInterface, delay_seconds: ?int}  $anchor
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

    /**
     * Reserve the first slot of one bulk send or recovery poll under a cache lock, so two
     * processes (for example bulk sends of different periods) cannot start on the same slot and
     * halve the SMTP spacing window.
     *
     * The pointer is shared with every other sender and honours the DB anchor, so a cold cache
     * still never schedules before the durable tail. A lock timeout only weakens the
     * cross-process spacing guarantee; it can never duplicate a delivery because the outbox
     * claim is authoritative.
     *
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

    /**
     * Claim one pending intent as queued and push its delayed job.
     *
     * Precondition: the caller resolved `$slot` through {@see reserveInitialSlot()} /
     * {@see nextSlot()} for this intent and owns the only claim of it (fresh reservation, or a
     * recovery batch holding the row lock). The claim is a conditional `pending -> queued`
     * update, so a concurrent poller that already claimed the intent makes this a no-op instead
     * of a second dispatch. A failed queue push returns the intent to `pending` before the
     * exception is rethrown.
     *
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

    /**
     * Recovery poll for intents left `pending` (queue push failed, request crashed mid-batch).
     *
     * Bounded to one batch inside the configured queue horizon, so a recovery run cannot flood
     * the queue with far-future jobs. A failed push rolls the batch back to `pending` (the
     * transaction) and the next poll retries. Queued, sending, sent, and failed intents are never
     * reclaimed here; their durable state is authoritative.
     */
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

    /**
     * Re-queue `queued` intents whose scheduled slot is long past, which means their queue job was
     * lost without invoking `failed()` (Redis flush, `queue:clear`, payload that cannot be
     * decoded). Leads are leased by rewriting `available_at` to now, so a row is re-queued at most
     * once per grace window instead of on every poll.
     *
     * Safe against duplicates: the job claim (`queued -> sending`) is a row-locked conditional
     * update, so if the original job ever runs it either wins the claim or sees `sending`/`sent`
     * and returns without sending.
     */
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

    /**
     * Opportunistic recovery for page loads: the repo runs no scheduler, so the QR status view
     * drives the net. Guarded by a cache lock so concurrent page loads cannot poll at once, and
     * bounded by the same batch/horizon as the command, so a page load can never flood the queue.
     */
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

    /**
     * Count intents left in `sending` past the grace period. They are never resent
     * automatically (the transport may already have accepted the mail); the count exists so the
     * dispatch command can surface the rows an operator must reconcile with
     * `recruitment:reconcile-qr-outbox`.
     *
     * Plan: `type=ref` on `recruitment_qr_outbox_status_available_idx` (the `status` prefix only),
     * then a filter on `updated_at`; there is no `(status, updated_at)` index.
     */
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

    /**
     * Count `queued` intents whose slot is long past, i.e. their job is missing.
     *
     * Plan: same `type=ref` scan on the `status` prefix of
     * `recruitment_qr_outbox_status_available_idx` (which also covers `available_at`).
     */
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
     * Read-modify-write of the shared slot pointer; must run inside the slot lock.
     *
     * @param  array{at: ?CarbonInterface, delay_seconds: ?int}  $anchor
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

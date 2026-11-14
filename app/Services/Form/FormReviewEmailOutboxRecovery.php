<?php

namespace App\Services\Form;

use App\Enums\EmailLogStatus;
use App\Jobs\DispatchFormReviewEmailOutboxJob;
use App\Models\EmailLog;
use App\Models\FormReviewEmailOutbox;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Recover pending or stale leases without sending a recipient twice. */
final class FormReviewEmailOutboxRecovery
{
    private const MAX_BATCH_SIZE = 100;

    private const DEFAULT_STALE_QUEUED_SECONDS = 900;

    /** Dispatch at most one bounded batch of due pending or stale dispatcher leases. */
    public function dispatchPending(int $batchSize = self::MAX_BATCH_SIZE): int
    {
        $batchSize = min(self::MAX_BATCH_SIZE, max(1, $batchSize));
        $now = now();
        $staleLeaseBefore = $now->copy()->subSeconds(
            (int) config('registration.form_review_outbox_stale_queued_seconds', self::DEFAULT_STALE_QUEUED_SECONDS),
        );

        $outboxIds = FormReviewEmailOutbox::query()
             ->where(static function ($query) use ($now, $staleLeaseBefore): void {
                 $query->where(static function ($pending) use ($now): void {
                     $pending
                         ->where('status', FormReviewEmailOutbox::STATUS_PENDING)
                         ->where(static function ($available) use ($now): void {
                             $available->whereNull('available_at')->orWhere('available_at', '<=', $now);
                         });
                 })->orWhere(static function ($dispatching) use ($staleLeaseBefore): void {
                     $dispatching
                         ->whereIn('status', [
                             FormReviewEmailOutbox::STATUS_DISPATCHING,
                             FormReviewEmailOutbox::STATUS_ENQUEUING,
                             FormReviewEmailOutbox::STATUS_SENDING,
                         ])
                         ->whereNotNull('queued_at')
                         ->where('queued_at', '<=', $staleLeaseBefore);
                 });
             })
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        $dispatched = 0;

        foreach ($outboxIds as $outboxId) {
            /** @var array{action: string, attempts?: int}|null $decision */
            $decision = DB::transaction(function () use ($outboxId, $now, $staleLeaseBefore): ?array {
                $outbox = FormReviewEmailOutbox::query()
                    ->whereKey($outboxId)
                    ->lockForUpdate()
                    ->first();

                if ($outbox === null) {
                    return null;
                }

                if ($outbox->status === FormReviewEmailOutbox::STATUS_PENDING
                    && ($outbox->available_at === null || $outbox->available_at->lessThanOrEqualTo($now))) {
                    return $this->claimForDispatch($outbox, $now);
                }

                if ($outbox->queued_at !== null
                    && $outbox->queued_at->lessThanOrEqualTo($staleLeaseBefore)
                    && in_array($outbox->status, [
                        FormReviewEmailOutbox::STATUS_DISPATCHING,
                        FormReviewEmailOutbox::STATUS_ENQUEUING,
                        FormReviewEmailOutbox::STATUS_SENDING,
                    ], true)) {
                    return $this->resolveStaleLease($outbox, $now);
                }

                return null;
            });

            if ($decision === null || $decision['action'] !== 'dispatch') {
                continue;
            }

            try {
                app(BusDispatcher::class)->dispatch(new DispatchFormReviewEmailOutboxJob(
                    (string) $outboxId,
                    true,
                    $decision['attempts'],
                ));
                $dispatched++;
            } catch (Throwable $exception) {
                FormReviewEmailOutbox::query()
                    ->whereKey($outboxId)
                    ->where('status', FormReviewEmailOutbox::STATUS_DISPATCHING)
                    ->where('attempts', $decision['attempts'])
                    ->update([
                        'status' => FormReviewEmailOutbox::STATUS_MANUAL_REVIEW,
                        'available_at' => null,
                        'last_error' => 'Recovery enqueue outcome is ambiguous: '.$exception::class,
                        'updated_at' => now(),
                    ]);

                Log::error('[FormReviewEmailOutboxRecovery] Recovery enqueue outcome is ambiguous.', [
                    'outbox_id' => $outboxId,
                    'exception_class' => $exception::class,
                ]);
            }
        }

        return $dispatched;
    }

    /** Claim a pending or stale dispatcher lease for one recovery dispatch. */
    private function claimForDispatch(FormReviewEmailOutbox $outbox, \Illuminate\Support\Carbon $now): array
    {
        $outbox->forceFill([
            'status' => FormReviewEmailOutbox::STATUS_DISPATCHING,
            'attempts' => ((int) $outbox->attempts) + 1,
            'queued_at' => $now,
            'available_at' => null,
            'last_error' => null,
        ])->save();

        return ['action' => 'dispatch', 'attempts' => (int) $outbox->attempts];
    }

    /** Resolve a stale lease without sending twice. */
    private function resolveStaleLease(FormReviewEmailOutbox $outbox, \Illuminate\Support\Carbon $now): array
    {
        $log = EmailLog::query()
            ->where('delivery_key', $outbox->delivery_key)
            ->lockForUpdate()
            ->first();

        if ($log !== null && $log->status === EmailLogStatus::Sent) {
            $outbox->forceFill([
                'status' => FormReviewEmailOutbox::STATUS_SENT,
                'sent_at' => $log->sent_at ?? $now,
                'last_error' => null,
            ])->save();

            return ['action' => 'resolved'];
        }

        if ($log === null && in_array($outbox->status, [
            FormReviewEmailOutbox::STATUS_DISPATCHING,
            FormReviewEmailOutbox::STATUS_SENDING,
        ], true)) {
            return $this->claimForDispatch($outbox, $now);
        }

        $outbox->forceFill([
            'status' => FormReviewEmailOutbox::STATUS_MANUAL_REVIEW,
            'available_at' => null,
            'last_error' => $outbox->status === FormReviewEmailOutbox::STATUS_ENQUEUING
                ? 'Recipient enqueue outcome is ambiguous; verify the queue before retrying.'
                : 'Delivery outcome is ambiguous; verify EmailLog and the mail transport before retrying.',
        ])->save();

        return ['action' => 'resolved'];
    }
}

<?php

namespace App\Jobs;

use App\Models\FormReviewEmailOutbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchFormReviewEmailOutboxJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 30;

    public function __construct(
        public string $outboxId,
        public bool $claimed = false,
        public ?int $claimAttempt = null,
    ) {
    }

    /** Queue exactly one recipient delivery from its durable transactional intent. */
    public function handle(): void
    {
        /** @var FormReviewEmailOutbox|null $outbox */
        $outbox = DB::transaction(function (): ?FormReviewEmailOutbox {
            $locked = FormReviewEmailOutbox::query()
                ->whereKey($this->outboxId)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status === FormReviewEmailOutbox::STATUS_SENT) {
                return null;
            }

            if ($this->claimed) {
                if ($this->claimAttempt === null
                    || (int) $locked->attempts !== $this->claimAttempt
                    || $locked->status !== FormReviewEmailOutbox::STATUS_DISPATCHING) {
                    return null;
                }
            } elseif ($locked->status !== FormReviewEmailOutbox::STATUS_PENDING) {
                return null;
            } else {
                $locked->forceFill([
                    'status' => FormReviewEmailOutbox::STATUS_DISPATCHING,
                    'attempts' => ((int) $locked->attempts) + 1,
                    'queued_at' => now(),
                    'available_at' => null,
                    'last_error' => null,
                ])->save();
            }

            $locked->forceFill([
                'status' => FormReviewEmailOutbox::STATUS_ENQUEUING,
                'last_error' => null,
            ])->save();

            return $locked;
        });

        if ($outbox === null) {
            return;
        }

        try {
            $job = match ($outbox->action) {
                FormReviewEmailOutbox::ACTION_ACCEPTED => new SendRegistrationAcceptedJob(
                    (string) $outbox->form_answer_id,
                    (string) $outbox->delivery_key,
                    (int) $outbox->attempts,
                ),
                FormReviewEmailOutbox::ACTION_REJECTED => new SendRegistrationRejectedJob(
                    (string) $outbox->form_answer_id,
                    (string) $outbox->delivery_key,
                    (int) $outbox->attempts,
                ),
                default => throw new \RuntimeException('Unsupported form review email action.'),
            };

            dispatch($job);

            FormReviewEmailOutbox::query()
                ->whereKey($outbox->id)
                ->where('status', FormReviewEmailOutbox::STATUS_ENQUEUING)
                ->where('attempts', (int) $outbox->attempts)
                ->update([
                    'status' => FormReviewEmailOutbox::STATUS_QUEUED,
                    'queued_at' => now(),
                    'available_at' => null,
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
        } catch (Throwable $exception) {
            FormReviewEmailOutbox::query()
                ->whereKey($outbox->id)
                ->where('status', FormReviewEmailOutbox::STATUS_ENQUEUING)
                ->where('attempts', (int) $outbox->attempts)
                ->update([
                    // A queue adapter can throw after accepting the payload. Retrying here would
                    // enqueue a second recipient job, so this boundary is fail-closed and requires
                    // queue inspection before a manual retry.
                    'status' => FormReviewEmailOutbox::STATUS_MANUAL_REVIEW,
                    'available_at' => null,
                    'last_error' => 'Recipient enqueue outcome is ambiguous: '.$exception::class,
                    'updated_at' => now(),
                ]);

            Log::error('[DispatchFormReviewEmailOutboxJob] Email job dispatch outcome is ambiguous.', [
                'outbox_id' => $outbox->id,
                'delivery_key' => $outbox->delivery_key,
                'exception_class' => $exception::class,
            ]);
        }
    }
}

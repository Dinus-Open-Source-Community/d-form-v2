<?php

namespace App\Jobs\Concerns;

use App\Enums\EmailLogStatus;
use App\Exceptions\RedactedMailDeliveryException;
use App\Models\EmailLog;
use App\Support\LogRedaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared delivery tracking for jobs that send email.
 *
 * The delivery key is generated once when the job is dispatched and is serialized inside the
 * queue payload, so every retry of the same job reuses it. The EmailLog row is reserved before
 * the transport call; a crash after the transport accepted the message therefore leaves a
 * `queued` row and the retry skips the send instead of delivering a duplicate.
 *
 * Jobs using this trait must also use `Illuminate\Foundation\Queue\Queueable`; `$this->job` and
 * `attempts()` are provided by that trait.
 */
trait TracksOutgoingEmail
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    /** Stable identity for one logical delivery; serialized with the job payload across retries. */
    public string $deliveryKey = '';

    protected function initializeEmailDelivery(?string $deliveryKey = null): void
    {
        $this->deliveryKey = $deliveryKey !== null && $deliveryKey !== ''
            ? $deliveryKey
            : (string) Str::uuid();
    }

    /**
     * Reserve (or resume) the EmailLog for this delivery.
     *
     * Returns null when the delivery is already sent or was interrupted mid-send; the caller
     * must not send again. A previously failed delivery is reset to queued and returned so the
     * retry can proceed.
     *
     * @param  array<string, mixed>  $attributes
     * @param  (callable(): ?EmailLog)|null  $adoptQueued  Existing queued row to adopt (bulk QR dispatch)
     */
    protected function reserveEmailLog(
        array $attributes,
        ?callable $adoptQueued = null,
        ?string $deliveryKey = null,
    ): ?EmailLog {
        $key = $deliveryKey ?? $this->deliveryKey;
        $existing = EmailLog::query()->where('delivery_key', $key)->first();

        if ($existing !== null) {
            if ($existing->status === EmailLogStatus::Failed) {
                $existing->forceFill([
                    'status' => EmailLogStatus::Queued,
                    'error_message' => null,
                    'sent_at' => null,
                ])->save();

                return $existing;
            }

            if ($existing->status === EmailLogStatus::Queued) {
                // The previous attempt reached the transport and died before confirming it.
                // Resending would risk a duplicate, so record the ambiguity and stop.
                $existing->forceFill([
                    'status' => EmailLogStatus::Failed,
                    'error_message' => 'Delivery was interrupted before confirmation; not resent to avoid duplicates.',
                    'sent_at' => null,
                ])->save();

                Log::warning('[TracksOutgoingEmail] Interrupted delivery skipped.', [
                    'job' => static::class,
                    'delivery_key' => $key,
                ]);
            }

            return null;
        }

        if ($adoptQueued !== null) {
            $candidate = $adoptQueued();

            if ($candidate instanceof EmailLog) {
                $candidate->forceFill([
                    'delivery_key' => $key,
                    'status' => EmailLogStatus::Queued,
                    'error_message' => null,
                    'sent_at' => null,
                ])->save();

                return $candidate;
            }
        }

        return EmailLog::query()->create(array_merge($attributes, [
            'delivery_key' => $key,
            'status' => EmailLogStatus::Queued,
            'error_message' => null,
            'sent_at' => null,
        ]));
    }

    protected function markEmailLogSent(EmailLog $log, ?string $recipientEmail = null): void
    {
        $log->forceFill([
            'recipient_email' => $recipientEmail ?? $log->recipient_email,
            'status' => EmailLogStatus::Sent,
            'error_message' => null,
            'sent_at' => now(),
        ])->save();
    }

    protected function markEmailLogFailed(EmailLog $log, Throwable $exception): void
    {
        $log->forceFill([
            'status' => EmailLogStatus::Failed,
            'error_message' => $this->safeDeliveryError($exception),
            'sent_at' => null,
        ])->save();
    }

    /** Mark a reserved delivery failed for a reason that is already public-safe. */
    protected function markEmailLogFailedWithReason(EmailLog $log, string $reason): void
    {
        $log->forceFill([
            'status' => EmailLogStatus::Failed,
            'error_message' => $reason,
            'sent_at' => null,
        ])->save();
    }

    /**
     * Record a delivery that cannot be attempted (for example no recipient address).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function markEmailLogUndeliverable(array $attributes, string $reason, ?string $deliveryKey = null): void
    {
        EmailLog::query()->create(array_merge($attributes, [
            'delivery_key' => $deliveryKey ?? $this->deliveryKey,
            'status' => EmailLogStatus::Failed,
            'error_message' => $reason,
            'sent_at' => null,
        ]));
    }

    /**
     * Terminal failure hook invoked by the queue after retries are exhausted.
     *
     * Marks every non-sent log owned by this dispatch and logs only non-PII identifiers.
     */
    public function failed(?Throwable $exception = null): void
    {
        $key = $this->deliveryKey;
        /** @var Collection<int, EmailLog> $logs */
        $logs = collect();

        if ($key !== '') {
            $logs = EmailLog::query()
                ->where('delivery_key', $key)
                ->orWhere('delivery_key', 'like', addcslashes($key, '%_').':%')
                ->limit(100)
                ->get();

            foreach ($logs as $log) {
                if ($log->status === EmailLogStatus::Sent) {
                    continue;
                }

                $log->forceFill([
                    'status' => EmailLogStatus::Failed,
                    'error_message' => $exception === null
                        ? 'Job failed permanently.'
                        : $this->safeDeliveryError($exception),
                    'sent_at' => null,
                ])->save();
            }
        }

        Log::error('[TracksOutgoingEmail] Email job failed permanently.', [
            'job' => static::class,
            'delivery_key' => $key,
            'job_id' => $this->job?->getJobId(),
            'attempt' => $this->attempts(),
            'exception_class' => $exception === null ? null : $exception::class,
            'recipients' => $logs
                ->map(fn (EmailLog $log): ?string => LogRedaction::email($log->recipient_email))
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    /** Keep transport diagnostics while delegating PII and credential redaction to LogRedaction. */
    protected function safeDeliveryError(Throwable $exception): string
    {
        return LogRedaction::exception($exception);
    }

    /** Rethrow the transport failure with a redacted message so framework logging and failed_jobs store no PII. */
    protected function throwRedacted(Throwable $exception): never
    {
        throw RedactedMailDeliveryException::fromThrowable($exception);
    }
}

<?php

namespace App\Models;

use App\Enums\EmailLogStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class FormReviewEmailOutbox extends Model
{
    use HasUuids;

    public const ACTION_ACCEPTED = 'accepted';

    public const ACTION_REJECTED = 'rejected';

    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_DISPATCHING = 'dispatching';

    public const STATUS_ENQUEUING = 'enqueuing';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * The queue boundary was ambiguous. This state is intentionally not automatically retried:
     * an operator must verify the queue before requesting a manual retry.
     */
    public const STATUS_MANUAL_REVIEW = 'manual_review';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'form_answer_id',
        'action',
        'delivery_key',
        'status',
        'attempts',
        'available_at',
        'queued_at',
        'sent_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function formAnswer(): BelongsTo
    {
        return $this->belongsTo(FormAnswer::class);
    }

    /**
     * Claim one recipient delivery and reserve its EmailLog in the same transaction.
     *
     * Reserving the log before releasing the outbox lock gives stale-sending recovery a reliable
     * boundary: a sending row without a log is a legacy row that never reached the transport.
     * A second recipient job fails closed while the first owns the sending state.
     *
     * @param array<string, mixed> $emailLogAttributes
     */
    public static function claimDelivery(
        string $deliveryKey,
        ?int $dispatchAttempt = null,
        array $emailLogAttributes = [],
    ): ?bool {
        return DB::transaction(function () use ($deliveryKey, $dispatchAttempt, $emailLogAttributes): ?bool {
            $outbox = self::query()
                ->where('delivery_key', $deliveryKey)
                ->lockForUpdate()
                ->first();

            if ($outbox === null) {
                return null;
            }

            if (in_array($outbox->status, [self::STATUS_SENT, self::STATUS_SENDING, self::STATUS_MANUAL_REVIEW], true)) {
                return false;
            }

            $isDispatcherOwned = $outbox->status === self::STATUS_ENQUEUING
                && $dispatchAttempt !== null
                && (int) $outbox->attempts === $dispatchAttempt;

            if (! $isDispatcherOwned
                && ! in_array($outbox->status, [self::STATUS_QUEUED, self::STATUS_FAILED], true)) {
                return false;
            }

            $existingLog = null;
            if ($emailLogAttributes !== []) {
                $existingLog = EmailLog::query()
                    ->where('delivery_key', $deliveryKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingLog !== null && $existingLog->status === EmailLogStatus::Sent) {
                    $outbox->forceFill([
                        'status' => self::STATUS_SENT,
                        'sent_at' => $existingLog->sent_at ?? now(),
                    ])->save();

                    return false;
                }

                if ($outbox->status === self::STATUS_FAILED
                    && $existingLog !== null
                    && $existingLog->status === EmailLogStatus::Queued) {
                    $outbox->forceFill([
                        'status' => self::STATUS_MANUAL_REVIEW,
                        'available_at' => null,
                        'last_error' => 'An unconfirmed EmailLog prevents an automatic retry.',
                    ])->save();

                    return false;
                }
            }

            $outbox->forceFill([
                'status' => self::STATUS_SENDING,
                'available_at' => null,
                'queued_at' => now(),
                'last_error' => null,
            ])->save();

            if ($emailLogAttributes !== []) {
                if ($existingLog === null) {
                    EmailLog::query()->create(array_merge($emailLogAttributes, [
                        'delivery_key' => $deliveryKey,
                        'status' => EmailLogStatus::Queued,
                        'error_message' => null,
                        'sent_at' => null,
                    ]));
                } elseif ($existingLog->status === EmailLogStatus::Failed) {
                    $existingLog->forceFill([
                        'status' => EmailLogStatus::Queued,
                        'error_message' => null,
                        'sent_at' => null,
                    ])->save();
                }
            }

            return true;
        });
    }

    /** Mark a durable intent as delivered without regressing an already-sent row. */
    public static function markSentByDeliveryKey(string $deliveryKey): void
    {
        self::query()
            ->where('delivery_key', $deliveryKey)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_QUEUED, self::STATUS_SENDING, self::STATUS_FAILED])
            ->update([
                'status' => self::STATUS_SENT,
                'sent_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);
    }

    /** Record a recoverable or terminal delivery failure without exposing transport details. */
    public static function markFailedByDeliveryKey(string $deliveryKey, string $reason): void
    {
        self::query()
            ->where('delivery_key', $deliveryKey)
            ->whereNotIn('status', [self::STATUS_SENT, self::STATUS_MANUAL_REVIEW])
            ->update([
                'status' => self::STATUS_FAILED,
                'last_error' => $reason,
                'updated_at' => now(),
            ]);
    }
}

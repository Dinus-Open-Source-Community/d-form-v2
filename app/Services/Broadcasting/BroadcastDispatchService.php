<?php

namespace App\Services\Broadcasting;

use App\Enums\EmailBroadcastRecipientStatus;
use App\Enums\EmailBroadcastStatus;
use App\Jobs\Broadcasting\SendBroadcastRecipientJob;
use App\Models\EmailBroadcast;
use Illuminate\Support\Facades\DB;

/**
 * Penjadwalan & dispatch queue per-recipient dengan random delay.
 * Tidak memakai sleep() di worker; memakai Laravel queue delay.
 */
class BroadcastDispatchService
{
    public function schedule(EmailBroadcast $broadcast): void
    {
        abort_unless($broadcast->status === EmailBroadcastStatus::Draft, 422, 'Hanya broadcast draft yang dapat dijadwalkan.');
        abort_unless($broadcast->scheduled_at !== null, 422, 'Schedule date/time wajib diisi.');
        abort_unless(filled($broadcast->subject) && filled($broadcast->content), 422, 'Subject dan content wajib diisi.');
        abort_if($broadcast->recipients()->count() === 0, 422, 'Recipient minimal 1.');

        $broadcast->forceFill(['status' => EmailBroadcastStatus::Scheduled])->save();
    }

    /**
     * Dipanggil scheduler tiap menit: dispatch broadcast yang waktunya tiba.
     * @return array<int, string> broadcast ids yang diproses
     */
    public function dispatchDue(): array
    {
        $due = EmailBroadcast::query()
            ->where('status', EmailBroadcastStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();

        $processed = [];

        foreach ($due as $broadcast) {
            $this->dispatch($broadcast);
            $processed[] = $broadcast->id;
        }

        return $processed;
    }

    public function dispatch(EmailBroadcast $broadcast): void
    {
        $broadcast->refresh();

        if ($broadcast->status !== EmailBroadcastStatus::Scheduled) {
            return;
        }

        DB::transaction(function () use ($broadcast): void {
            $locked = EmailBroadcast::query()
                ->whereKey($broadcast->id)
                ->where('status', EmailBroadcastStatus::Scheduled->value)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return;
            }

            $locked->forceFill([
                'status' => EmailBroadcastStatus::Processing,
                'started_at' => now(),
            ])->save();
        });

        $broadcast->refresh();

        $min = max(0, (int) $broadcast->delay_min);
        $max = max($min, (int) $broadcast->delay_max);

        $ids = $broadcast->recipients()
            ->where('status', EmailBroadcastRecipientStatus::Pending->value)
            ->pluck('id');

        foreach ($ids as $recipientId) {
            $delaySeconds = $max === $min ? $min : random_int($min, $max);

            SendBroadcastRecipientJob::dispatch((string) $recipientId)
                ->delay(now()->addSeconds($delaySeconds))
                ->onQueue('broadcasts');
        }

        // Edge: tanpa recipient pending → langsung completed.
        if ($ids->isEmpty()) {
            $this->maybeComplete($broadcast);
        }
    }

    public function cancel(EmailBroadcast $broadcast): void
    {
        abort_unless(
            in_array($broadcast->status, [EmailBroadcastStatus::Scheduled, EmailBroadcastStatus::Processing], true),
            422,
            'Hanya broadcast scheduled/processing yang dapat dibatalkan.'
        );

        DB::transaction(function () use ($broadcast): void {
            $broadcast->forceFill([
                'status' => EmailBroadcastStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            // Pending → cancelled. Yang sudah sent tetap sent.
            $broadcast->recipients()
                ->whereIn('status', [
                    EmailBroadcastRecipientStatus::Pending->value,
                    EmailBroadcastRecipientStatus::Processing->value,
                ])
                ->update([
                    'status' => EmailBroadcastRecipientStatus::Cancelled->value,
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * @return int jumlah recipient yang di-retry
     */
    public function retryFailed(EmailBroadcast $broadcast): int
    {
        $failedIds = $broadcast->recipients()
            ->where('status', EmailBroadcastRecipientStatus::Failed->value)
            ->pluck('id');

        if ($failedIds->isEmpty()) {
            return 0;
        }

        // Jika broadcast sudah completed/processing, buka kembali ke processing agar job jalan.
        if (in_array($broadcast->status, [EmailBroadcastStatus::Completed, EmailBroadcastStatus::Processing], true)) {
            $broadcast->forceFill([
                'status' => EmailBroadcastStatus::Processing,
                'completed_at' => null,
            ])->save();
        }

        $min = max(0, (int) $broadcast->delay_min);
        $max = max($min, (int) $broadcast->delay_max);

        foreach ($failedIds as $recipientId) {
            EmailBroadcastRecipient::query()->whereKey($recipientId)->update([
                'status' => EmailBroadcastRecipientStatus::Pending->value,
                'error_message' => null,
                'failed_at' => null,
                'updated_at' => now(),
            ]);

            $delaySeconds = $max === $min ? $min : random_int($min, $max);

            SendBroadcastRecipientJob::dispatch((string) $recipientId)
                ->delay(now()->addSeconds($delaySeconds))
                ->onQueue('broadcasts');
        }

        return $failedIds->count();
    }

    public function maybeComplete(EmailBroadcast $broadcast): void
    {
        $broadcast->refresh();

        if ($broadcast->status !== EmailBroadcastStatus::Processing) {
            return;
        }

        $remaining = $broadcast->recipients()
            ->whereIn('status', [
                EmailBroadcastRecipientStatus::Pending->value,
                EmailBroadcastRecipientStatus::Processing->value,
            ])
            ->count();

        if ($remaining > 0) {
            return;
        }

        $sent = $broadcast->recipients()->where('status', EmailBroadcastRecipientStatus::Sent->value)->count();
        $failed = $broadcast->recipients()->where('status', EmailBroadcastRecipientStatus::Failed->value)->count();

        $broadcast->forceFill([
            'status' => EmailBroadcastStatus::Completed,
            'completed_at' => now(),
            'total_sent' => $sent,
            'total_failed' => $failed,
        ])->save();
    }
}

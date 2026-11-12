<?php

namespace App\Services\Broadcasting;

use App\Enums\EmailBroadcastRecipientStatus;
use App\Enums\EmailBroadcastStatus;
use App\Jobs\Broadcasting\SendBroadcastRecipientJob;
use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
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
     * @return array<int, string> broadcast ids yang berhasil diklaim dan diproses
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
            if ($this->dispatch($broadcast)) {
                $processed[] = $broadcast->id;
            }
        }

        return $processed;
    }

    /**
     * Klaim broadcast scheduled secara atomik lalu dispatch job per recipient.
     *
     * Claim memakai conditional update supaya dua worker tidak memproses
     * broadcast yang sama. Job hanya di-dispatch bila claim menang.
     *
     * @return bool true bila broadcast berhasil diklaim oleh pemanggil ini
     */
    public function dispatch(EmailBroadcast $broadcast): bool
    {
        $claimed = EmailBroadcast::query()
            ->whereKey($broadcast->getKey())
            ->where('status', EmailBroadcastStatus::Scheduled->value)
            ->update([
                'status' => EmailBroadcastStatus::Processing->value,
                'started_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

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

        return true;
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
     * Reset recipient failed → pending dan dispatch ulang.
     *
     * Reset memakai conditional update supaya dua pemanggil bersamaan tidak
     * meng-queue job yang sama dua kali; job hanya di-dispatch untuk baris
     * yang benar-benar berhasil di-reset oleh pemanggil ini.
     *
     * @return int jumlah recipient yang benar-benar di-retry
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

        $retried = 0;

        foreach ($failedIds as $recipientId) {
            $reset = EmailBroadcastRecipient::query()
                ->whereKey($recipientId)
                ->where('status', EmailBroadcastRecipientStatus::Failed->value)
                ->update([
                    'status' => EmailBroadcastRecipientStatus::Pending->value,
                    'error_message' => null,
                    'failed_at' => null,
                    'updated_at' => now(),
                ]);

            if ($reset === 0) {
                continue;
            }

            $retried++;
            $delaySeconds = $max === $min ? $min : random_int($min, $max);

            SendBroadcastRecipientJob::dispatch((string) $recipientId)
                ->delay(now()->addSeconds($delaySeconds))
                ->onQueue('broadcasts');
        }

        return $retried;
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

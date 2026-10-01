<?php

namespace App\Services\Broadcast;

use App\Enums\EmailLogStatus;
use App\Jobs\SendBroadcastJob;
use App\Models\Broadcast;
use App\Models\EmailLog;

final class BroadcastDispatchService
{
    /** Kirim-sekarang: kunci processing + antre satu job per penerima snapshot. */
    public function sendNow(Broadcast $broadcast): int
    {
        if (! in_array($broadcast->status, [Broadcast::STATUS_DRAFT, Broadcast::STATUS_SCHEDULED], true)) {
            return 0;
        }

        $broadcast->update(['status' => Broadcast::STATUS_PROCESSING]);

        $recipients = array_values($broadcast->recipient_snapshot['recipients'] ?? []);
        $stepDelay = max(0, (int) $broadcast->send_delay_seconds);

        foreach ($recipients as $index => $recipient) {
            $email = $recipient['email'] ?? null;

            if (! is_string($email) || $email === '') {
                continue;
            }

            $pending = SendBroadcastJob::dispatch($broadcast->id, $email);

            if ($stepDelay > 0) {
                $pending->delay(now()->addSeconds($stepDelay * $index));
            }
        }

        return count($recipients);
    }

    /** Kirim-ulang hanya penerima gagal; kunci processing bila ada yang dikirim-ulang. */
    public function retryFailed(Broadcast $broadcast): int
    {
        $failed = $this->failedEmails($broadcast);

        foreach ($failed as $email) {
            SendBroadcastJob::dispatch($broadcast->id, $email);
        }

        if (count($failed) > 0) {
            $broadcast->update(['status' => Broadcast::STATUS_PROCESSING]);
        }

        return count($failed);
    }

    /** Email snapshot yang log terakhirnya Failed. */
    private function failedEmails(Broadcast $broadcast): array
    {
        $emails = [];

        foreach (array_values($broadcast->recipient_snapshot['recipients'] ?? []) as $recipient) {
            $email = $recipient['email'] ?? null;

            if (is_string($email) && $email !== '') {
                $emails[] = $email;
            }
        }

        if ($emails === []) {
            return [];
        }

        $latestByEmail = EmailLog::query()
            ->where('broadcast_id', $broadcast->id)
            ->whereIn('recipient_email', $emails)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('recipient_email')
            ->map(fn ($group) => $group->last());

        $failed = [];

        foreach ($emails as $email) {
            if (($latestByEmail->get($email)?->status ?? null) === EmailLogStatus::Failed) {
                $failed[] = $email;
            }
        }

        return $failed;
    }
}

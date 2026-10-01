<?php

namespace App\Services\Broadcast;

use App\Jobs\SendBroadcastJob;
use App\Models\Broadcast;

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
}

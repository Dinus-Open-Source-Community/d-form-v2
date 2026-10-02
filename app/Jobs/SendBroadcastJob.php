<?php

namespace App\Jobs;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Mail\BroadcastMail;
use App\Models\Broadcast;
use App\Models\EmailLog;
use App\Services\Broadcast\BroadcastDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBroadcastJob implements ShouldQueue
{
    use AppliesOutgoingEmailDelay;
    use Queueable;

    public function __construct(
        public string $broadcastId,
        public string $recipientEmail,
    ) {
    }

    public function handle(BroadcastDispatchService $dispatch): void
    {
        // Fitur broadcast dinonaktifkan (config/features.php).
        // Job lama yang masih mengendap di antrean sengaja dibuang tanpa mengirim.
        // Logika asli di bawah dipertahankan agar bisa aktif lagi.
        if (! config('features.broadcast', false)) {
            Log::notice('[SendBroadcastJob] Fitur broadcast dinonaktifkan; job dibuang.', [
                'broadcast_id' => $this->broadcastId,
                'recipient_email' => $this->recipientEmail,
            ]);

            return;
        }

        $broadcast = Broadcast::query()->find($this->broadcastId);

        if ($broadcast === null) {
            Log::warning('[SendBroadcastJob] Broadcast not found.', [
                'broadcast_id' => $this->broadcastId,
            ]);

            return;
        }

        try {
            $this->applyOutgoingEmailJitter();

            Mail::to($this->recipientEmail)->send(new BroadcastMail($broadcast, $this->recipientEmail));

            EmailLog::query()->create([
                'broadcast_id' => $broadcast->id,
                'event_id' => $broadcast->event_id,
                'user_id' => $broadcast->created_by,
                'recipient_email' => $this->recipientEmail,
                'status' => EmailLogStatus::Sent,
                'notification_type' => EmailNotificationType::BroadcastSent,
                'error_message' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            EmailLog::query()->create([
                'broadcast_id' => $broadcast->id,
                'event_id' => $broadcast->event_id,
                'user_id' => $broadcast->created_by,
                'recipient_email' => $this->recipientEmail,
                'status' => EmailLogStatus::Failed,
                'notification_type' => EmailNotificationType::BroadcastSent,
                'error_message' => $e->getMessage(),
                'sent_at' => null,
            ]);

            Log::error('[SendBroadcastJob] Email send failed.', [
                'broadcast_id' => $broadcast->id,
                'recipient_email' => $this->recipientEmail,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        } finally {
            $dispatch->markSentIfComplete($broadcast);
        }
    }
}

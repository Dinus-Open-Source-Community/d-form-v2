<?php

namespace App\Jobs\Broadcasting;

use App\Enums\EmailBroadcastRecipientStatus;
use App\Mail\BroadcastMail;
use App\Models\EmailBroadcastRecipient;
use App\Services\Broadcasting\BroadcastDispatchService;
use App\Services\Broadcasting\BroadcastPersonalization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Satu job per recipient (PRD §30). Automatic retry maks 3 attempt.
 */
class SendBroadcastRecipientJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public string $recipientId,
    ) {
    }

    public function handle(
        BroadcastPersonalization $personalization,
        BroadcastDispatchService $dispatchService,
    ): void {
        $recipient = EmailBroadcastRecipient::query()
            ->with(['broadcast.event', 'broadcast.attachments'])
            ->find($this->recipientId);

        if ($recipient === null) {
            return;
        }

        $broadcast = $recipient->broadcast;

        // Broadcast dibatalkan → tandai cancelled, jangan kirim.
        if ($broadcast->status->value === 'cancelled') {
            if (in_array($recipient->status->value, ['pending', 'processing'], true)) {
                $recipient->forceFill(['status' => EmailBroadcastRecipientStatus::Cancelled])->save();
            }

            return;
        }

        // Hanya proses pending (retry manual me-reset failed → pending).
        if (! in_array($recipient->status->value, ['pending', 'processing'], true)) {
            return;
        }

        $recipient->forceFill([
            'status' => EmailBroadcastRecipientStatus::Processing,
            'attempts' => $this->attempts(),
        ])->save();

        $eventName = $personalization->eventNameFor($broadcast);
        $subject = $personalization->render($broadcast->subject, $recipient->name, $eventName);
        $html = $personalization->renderHtml($broadcast->content, $recipient->name, $eventName);
        $text = BroadcastMail::textFallback($broadcast->content ?? '');
        $text = $personalization->render($text, $recipient->name, $eventName);

        try {
            Mail::to($recipient->email)->send(
                new BroadcastMail($subject, $html, $text, $broadcast->id)
            );

            $recipient->forceFill([
                'status' => EmailBroadcastRecipientStatus::Sent,
                'attempts' => $this->attempts(),
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();

            $broadcast->increment('total_sent');
        } catch (\Throwable $e) {
            $isLastAttempt = $this->attempts() >= $this->tries;

            Log::error('[SendBroadcastRecipientJob] send failed.', [
                'recipient_id' => $recipient->id,
                'broadcast_id' => $broadcast->id,
                'email' => $recipient->email,
                'attempt' => $this->attempts(),
                'exception' => $e->getMessage(),
            ]);

            $recipient->forceFill([
                'attempts' => $this->attempts(),
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            if ($isLastAttempt) {
                $recipient->forceFill([
                    'status' => EmailBroadcastRecipientStatus::Failed,
                    'failed_at' => now(),
                ])->save();

                $broadcast->increment('total_failed');
            }

            throw $e;
        } finally {
            $dispatchService->maybeComplete($broadcast);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $recipient = EmailBroadcastRecipient::query()->find($this->recipientId);

        if ($recipient === null) {
            return;
        }

        // Pastikan status final failed bila retry habis tanpa mencapai catch terakhir.
        if ($recipient->status !== EmailBroadcastRecipientStatus::Sent) {
            $recipient->forceFill([
                'status' => EmailBroadcastRecipientStatus::Failed,
                'failed_at' => $recipient->failed_at ?? now(),
                'error_message' => $recipient->error_message ?? mb_substr($exception->getMessage(), 0, 1000),
            ])->save();
        }

        app(BroadcastDispatchService::class)->maybeComplete($recipient->broadcast);
    }
}

<?php

namespace App\Jobs;

use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Enums\EmailNotificationType;
use App\Enums\FormAnswerReviewStatus;
use App\Mail\RegistrationRejectedMail;
use App\Models\FormAnswer;
use App\Services\Registration\FormAnswerRecipientResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRegistrationRejectedJob implements ShouldQueue
{
    use AppliesOutgoingEmailDelay;
    use Queueable;
    use TracksOutgoingEmail;

    public function __construct(
        public string $formAnswerId,
    ) {
        $this->initializeEmailDelivery();
    }

    public function handle(FormAnswerRecipientResolver $recipientResolver): void
    {
        $submission = FormAnswer::query()
            ->with(['form.event', 'user'])
            ->find($this->formAnswerId);

        if ($submission === null) {
            Log::warning('[SendRegistrationRejectedJob] FormAnswer not found.', [
                'form_answer_id' => $this->formAnswerId,
            ]);

            return;
        }

        if ($submission->review_status !== FormAnswerReviewStatus::Rejected) {
            Log::warning('[SendRegistrationRejectedJob] Submission is not rejected.', [
                'form_answer_id' => $submission->id,
                'review_status' => $submission->review_status?->value,
            ]);

            return;
        }

        $recipientEmail = $recipientResolver->email($submission);
        $event = $submission->form->event;

        $logAttributes = [
            'form_answer_id' => $submission->id,
            'event_id' => $event->id,
            'user_id' => $recipientResolver->userIdForLog($submission),
            'recipient_email' => $recipientEmail ?? '',
            'notification_type' => EmailNotificationType::RegistrationRejected,
        ];

        if ($recipientEmail === null || $recipientEmail === '') {
            $this->markEmailLogUndeliverable($logAttributes, 'No recipient email address configured.');

            Log::warning('[SendRegistrationRejectedJob] No recipient email.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $this->applyOutgoingEmailJitter();

        $log = $this->reserveEmailLog($logAttributes);

        if ($log === null) {
            return;
        }

        try {
            Mail::to($recipientEmail)->send(new RegistrationRejectedMail($submission));

            $this->markEmailLogSent($log, $recipientEmail);
        } catch (\Throwable $e) {
            $this->markEmailLogFailed($log, $e);

            Log::error('[SendRegistrationRejectedJob] Email send failed.', [
                'notification_type' => EmailNotificationType::RegistrationRejected->value,
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'delivery_key' => $this->deliveryKey,
                'exception_class' => $e::class,
            ]);

            throw $e;
        }
    }
}

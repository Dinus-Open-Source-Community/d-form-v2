<?php

namespace App\Jobs;

use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Enums\EmailNotificationType;
use App\Enums\EmailLogStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Mail\RegistrationAcceptedMail;
use App\Models\FormAnswer;
use App\Models\EmailLog;
use App\Models\FormReviewEmailOutbox;
use App\Services\Registration\FormAnswerRecipientResolver;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRegistrationAcceptedJob implements ShouldQueue
{
    use AppliesOutgoingEmailDelay;
    use Queueable;
    use TracksOutgoingEmail;

    public function __construct(
        public string $formAnswerId,
        ?string $deliveryKey = null,
        public ?int $dispatchAttempt = null,
    ) {
        $this->initializeEmailDelivery($deliveryKey);
    }

    public function handle(
        RegistrationQrPngGenerator $qrGenerator,
        FormAnswerRecipientResolver $recipientResolver,
    ): void {
        $submission = FormAnswer::query()
            ->with(['form.event', 'user'])
            ->find($this->formAnswerId);

        if ($submission === null) {
            $this->markOutboxFailed('Submission was not found.');
            Log::warning('[SendRegistrationAcceptedJob] FormAnswer not found.', [
                'form_answer_id' => $this->formAnswerId,
            ]);

            return;
        }

        if ($submission->review_status !== FormAnswerReviewStatus::Accepted) {
            $this->markOutboxFailed('Submission is not accepted.');
            Log::warning('[SendRegistrationAcceptedJob] Submission is not accepted.', [
                'form_answer_id' => $submission->id,
                'review_status' => $submission->review_status?->value,
            ]);

            return;
        }

        $registrationCode = $submission->registration_code;
        if ($registrationCode === null || $registrationCode === '') {
            $this->markOutboxFailed('Registration code is missing.');
            Log::warning('[SendRegistrationAcceptedJob] Missing registration_code.', [
                'form_answer_id' => $submission->id,
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
            'notification_type' => EmailNotificationType::RegistrationAccepted,
        ];

        if ($recipientEmail === null || $recipientEmail === '') {
            $this->markEmailLogUndeliverable($logAttributes, 'No recipient email address configured.');
            $this->markOutboxFailed('No recipient email address configured.');

            Log::warning('[SendRegistrationAcceptedJob] No recipient email.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        if (FormReviewEmailOutbox::claimDelivery(
            $this->deliveryKey,
            $this->dispatchAttempt,
            $logAttributes,
        ) === false) {
            return;
        }

        $png = $qrGenerator->pngForSubmission($submission->id);

        $this->applyOutgoingEmailJitter();

        $log = $this->reserveEmailLog($logAttributes, null, $this->deliveryKey, true);

        if ($log === null) {
            if (EmailLog::query()
                ->where('delivery_key', $this->deliveryKey)
                ->where('status', EmailLogStatus::Sent)
                ->exists()) {
                FormReviewEmailOutbox::markSentByDeliveryKey($this->deliveryKey);
            }

            return;
        }

        try {
            Mail::to($recipientEmail)->send(
                new RegistrationAcceptedMail($submission, $png, $registrationCode)
            );

            $this->markEmailLogSent($log, $recipientEmail);
            FormReviewEmailOutbox::markSentByDeliveryKey($this->deliveryKey);
        } catch (\Throwable $e) {
            $this->markEmailLogFailed($log, $e);
            FormReviewEmailOutbox::markFailedByDeliveryKey($this->deliveryKey, $this->safeDeliveryError($e));

            Log::error('[SendRegistrationAcceptedJob] Email send failed.', [
                'notification_type' => EmailNotificationType::RegistrationAccepted->value,
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'delivery_key' => $this->deliveryKey,
                'exception_class' => $e::class,
            ]);

            $this->throwRedacted($e);
        }
    }

    private function markOutboxFailed(string $reason): void
    {
        FormReviewEmailOutbox::markFailedByDeliveryKey($this->deliveryKey, $reason);
    }
}

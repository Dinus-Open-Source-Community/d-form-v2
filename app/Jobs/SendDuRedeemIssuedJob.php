<?php

namespace App\Jobs;

use App\Enums\DuRedeemStatus;
use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\FormAnswerReviewStatus;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Mail\DuRedeemIssuedMail;
use App\Models\DuRedeemIssuance;
use App\Models\EmailLog;
use App\Models\FormAnswer;
use App\Models\FormReviewEmailOutbox;
use App\Services\Registration\FormAnswerRecipientResolver;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDuRedeemIssuedJob implements ShouldQueue
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
            ->with(['form.event', 'user', 'duRedeemIssuance'])
            ->find($this->formAnswerId);

        if ($submission === null) {
            $this->markOutboxFailed('Submission was not found.');

            Log::warning('[SendDuRedeemIssuedJob] FormAnswer not found.', [
                'form_answer_id' => $this->formAnswerId,
            ]);

            return;
        }

        if ($submission->review_status !== FormAnswerReviewStatus::Accepted) {
            $this->markOutboxFailed('Submission is not accepted.');

            Log::warning('[SendDuRedeemIssuedJob] Submission is not accepted.', [
                'form_answer_id' => $submission->id,
                'review_status' => $submission->review_status?->value,
            ]);

            return;
        }

        $issuance = $submission->duRedeemIssuance;
        $redeemCode = $issuance?->redeem_code;

        if (! $issuance instanceof DuRedeemIssuance
            || $issuance->status !== DuRedeemStatus::Issued
            || ! is_string($redeemCode)
            || trim($redeemCode) === '') {
            $this->markOutboxFailed('DU redeem code is not issued.');

            Log::warning('[SendDuRedeemIssuedJob] DU redeem code is not issued.', [
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
            'notification_type' => EmailNotificationType::DuRedeemIssued,
        ];

        if ($recipientEmail === null || $recipientEmail === '') {
            $this->markEmailLogUndeliverable($logAttributes, 'No recipient email address configured.');
            $this->markOutboxFailed('No recipient email address configured.');

            Log::warning('[SendDuRedeemIssuedJob] No recipient email.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        if (FormReviewEmailOutbox::claimDelivery(
            $this->deliveryKey,
            $this->dispatchAttempt,
            $logAttributes,
        ) !== true) {
            return;
        }

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
            $qrPng = $qrGenerator->pngForSubmission($submission->id);

            Mail::to($recipientEmail)->send(
                new DuRedeemIssuedMail($submission, $qrPng, $redeemCode)
            );

            $this->markEmailLogSent($log, $recipientEmail);
            FormReviewEmailOutbox::markSentByDeliveryKey($this->deliveryKey);
        } catch (\Throwable $exception) {
            $this->markEmailLogFailed($log, $exception);
            FormReviewEmailOutbox::markFailedByDeliveryKey(
                $this->deliveryKey,
                $this->safeDeliveryError($exception),
            );

            Log::error('[SendDuRedeemIssuedJob] Email send failed.', [
                'notification_type' => EmailNotificationType::DuRedeemIssued->value,
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'delivery_key' => $this->deliveryKey,
                'exception_class' => $exception::class,
            ]);

            $this->throwRedacted($exception);
        }
    }

    private function markOutboxFailed(string $reason): void
    {
        FormReviewEmailOutbox::markFailedByDeliveryKey($this->deliveryKey, $reason);
    }
}

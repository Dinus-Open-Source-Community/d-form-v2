<?php

namespace App\Jobs;

use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Enums\EmailNotificationType;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\RegistrationRole;
use App\Mail\RegistrationConfirmationMail;
use App\Models\FormAnswer;
use App\Services\Registration\FormAnswerRecipientResolver;
use App\Services\Registration\RegistrationAnswersSummarizer;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRegistrationConfirmationJob implements ShouldQueue
{
    use AppliesOutgoingEmailDelay;
    use Queueable;
    use TracksOutgoingEmail;

    public function __construct(
        public string $formAnswerId,
    ) {
        $this->initializeEmailDelivery();
    }

    public function handle(
        RegistrationAnswersSummarizer $summarizer,
        RegistrationQrPngGenerator $qrGenerator,
        FormAnswerRecipientResolver $recipientResolver,
    ): void {
        $submission = FormAnswer::query()
            ->with(['form.event', 'user'])
            ->find($this->formAnswerId);

        if ($submission === null) {
            Log::warning('[SendRegistrationConfirmationJob] FormAnswer not found.', [
                'form_answer_id' => $this->formAnswerId,
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
            'notification_type' => EmailNotificationType::RegistrationSubmitted,
        ];

        if ($recipientEmail === null || $recipientEmail === '') {
            $this->markEmailLogUndeliverable($logAttributes, 'No recipient email address configured.');

            Log::warning('[SendRegistrationConfirmationJob] No recipient email.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $answersSummary = $summarizer->summarize($submission);

        $isTeamOrBundleLeader = $submission->registration_role === RegistrationRole::Leader;
        $isAccepted = $submission->review_status === FormAnswerReviewStatus::Accepted;

        // Only generate QR code for accepted submissions (except team/bundle leaders)
        $qrPng = ($isTeamOrBundleLeader || ! $isAccepted)
            ? null
            : $qrGenerator->pngForSubmission($submission->id);

        $this->applyOutgoingEmailJitter();

        $log = $this->reserveEmailLog($logAttributes);

        if ($log === null) {
            return;
        }

        try {
            Mail::to($recipientEmail)->send(
                new RegistrationConfirmationMail($submission, $answersSummary, $qrPng)
            );

            $this->markEmailLogSent($log, $recipientEmail);
        } catch (\Throwable $e) {
            $this->markEmailLogFailed($log, $e);

            Log::error('[SendRegistrationConfirmationJob] Email send failed.', [
                'notification_type' => EmailNotificationType::RegistrationSubmitted->value,
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'delivery_key' => $this->deliveryKey,
                'exception_class' => $e::class,
            ]);

            throw $e;
        }
    }
}

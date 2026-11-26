<?php

namespace App\Jobs;

use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\RegistrationRole;
use App\Models\FormAnswer;
use App\Services\Form\FormSessionSchedule;
use App\Services\Registration\FormAnswerRecipientResolver;
use App\Services\Registration\FormReceiptContext;
use App\Services\Registration\FormReceiptMailResolver;
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
        ?FormReceiptMailResolver $mailResolver = null,
        ?FormSessionSchedule $schedule = null,
    ): void {
        $mailResolver ??= app(FormReceiptMailResolver::class);
        $schedule ??= app(FormSessionSchedule::class);
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

        $notificationType = $mailResolver->notificationType($submission->form);
        $logAttributes = [
            'form_answer_id' => $submission->id,
            'event_id' => $event->id,
            'user_id' => $recipientResolver->userIdForLog($submission),
            'recipient_email' => $recipientEmail ?? '',
            'notification_type' => $notificationType,
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

        $isDKelas = $submission->form->purpose()->value === 'd_kelas';
        $qrPng = $isDKelas || (! $isTeamOrBundleLeader && $isAccepted)
            ? $qrGenerator->pngForSubmission($submission->id)
            : null;
        $context = new FormReceiptContext(
            $answersSummary,
            $qrPng,
            $isDKelas ? $schedule->datesFor($submission->form) : [],
        );

        $this->applyOutgoingEmailJitter();

        $log = $this->reserveEmailLog($logAttributes);

        if ($log === null) {
            return;
        }

        try {
            Mail::to($recipientEmail)->send(
                $mailResolver->make($submission, $context)
            );

            $this->markEmailLogSent($log, $recipientEmail);
        } catch (\Throwable $e) {
            $this->markEmailLogFailed($log, $e);

            Log::error('[SendRegistrationConfirmationJob] Email send failed.', [
                'notification_type' => $notificationType->value,
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'delivery_key' => $this->deliveryKey,
                'exception_class' => $e::class,
            ]);

            $this->throwRedacted($e);
        }
    }
}

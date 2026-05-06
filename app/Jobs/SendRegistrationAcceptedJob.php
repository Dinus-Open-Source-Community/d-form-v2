<?php

namespace App\Jobs;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\FormAnswerReviewStatus;
use App\Mail\RegistrationAcceptedMail;
use App\Models\EmailLog;
use App\Models\FormAnswer;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRegistrationAcceptedJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $formAnswerId,
    ) {}

    public function handle(RegistrationQrPngGenerator $qrGenerator): void
    {
        $submission = FormAnswer::query()
            ->with(['form.event', 'user'])
            ->find($this->formAnswerId);

        if ($submission === null) {
            Log::warning('[SendRegistrationAcceptedJob] FormAnswer not found.', [
                'form_answer_id' => $this->formAnswerId,
            ]);

            return;
        }

        if ($submission->review_status !== FormAnswerReviewStatus::Accepted) {
            Log::warning('[SendRegistrationAcceptedJob] Submission is not accepted.', [
                'form_answer_id' => $submission->id,
                'review_status' => $submission->review_status?->value,
            ]);

            return;
        }

        $registrationCode = $submission->registration_code;
        if ($registrationCode === null || $registrationCode === '') {
            Log::warning('[SendRegistrationAcceptedJob] Missing registration_code.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $user = $submission->user;
        if ($user === null) {
            Log::warning('[SendRegistrationAcceptedJob] Submission has no user.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $event = $submission->form->event;

        if ($user->email === '') {
            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'recipient_email' => '',
                'status' => EmailLogStatus::Failed,
                'notification_type' => EmailNotificationType::RegistrationAccepted,
                'error_message' => 'User has no email address configured.',
                'sent_at' => null,
            ]);

            Log::warning('[SendRegistrationAcceptedJob] No recipient email.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        try {
            $png = $qrGenerator->pngForSubmission($submission->id);
            Mail::to($user->email)->send(
                new RegistrationAcceptedMail($submission, $png, $registrationCode)
            );

            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'recipient_email' => $user->email,
                'status' => EmailLogStatus::Sent,
                'notification_type' => EmailNotificationType::RegistrationAccepted,
                'error_message' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'recipient_email' => $user->email,
                'status' => EmailLogStatus::Failed,
                'notification_type' => EmailNotificationType::RegistrationAccepted,
                'error_message' => $e->getMessage(),
                'sent_at' => null,
            ]);

            Log::error('[SendRegistrationAcceptedJob] Send failed.', [
                'form_answer_id' => $submission->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}

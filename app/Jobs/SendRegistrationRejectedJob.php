<?php

namespace App\Jobs;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\FormAnswerReviewStatus;
use App\Mail\RegistrationRejectedMail;
use App\Models\EmailLog;
use App\Models\FormAnswer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRegistrationRejectedJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $formAnswerId,
    ) {}

    public function handle(): void
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

        $user = $submission->user;
        if ($user === null) {
            Log::warning('[SendRegistrationRejectedJob] Submission has no user.', [
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
                'notification_type' => EmailNotificationType::RegistrationRejected,
                'error_message' => 'User has no email address configured.',
                'sent_at' => null,
            ]);

            Log::warning('[SendRegistrationRejectedJob] No recipient email.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        try {
            Mail::to($user->email)->send(new RegistrationRejectedMail($submission));

            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'recipient_email' => $user->email,
                'status' => EmailLogStatus::Sent,
                'notification_type' => EmailNotificationType::RegistrationRejected,
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
                'notification_type' => EmailNotificationType::RegistrationRejected,
                'error_message' => $e->getMessage(),
                'sent_at' => null,
            ]);

            Log::error('[SendRegistrationRejectedJob] Send failed.', [
                'form_answer_id' => $submission->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}

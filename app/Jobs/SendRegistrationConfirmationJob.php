<?php

namespace App\Jobs;

use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\RegistrationRole;
use App\Mail\RegistrationConfirmationMail;
use App\Models\EmailLog;
use App\Models\FormAnswer;
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

    public function __construct(
        public string $formAnswerId,
    ) {
    }

    public function handle(
        RegistrationAnswersSummarizer $summarizer,
        RegistrationQrPngGenerator $qrGenerator,
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

        $user = $submission->user;
        if ($user === null) {
            Log::warning('[SendRegistrationConfirmationJob] Submission has no user.', [
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
                'notification_type' => EmailNotificationType::RegistrationSubmitted,
                'error_message' => 'User has no email address configured.',
                'sent_at' => null,
            ]);

            Log::warning('[SendRegistrationConfirmationJob] No recipient email.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $answersSummary = $summarizer->summarize($submission);

        $isTeamOrBundleLeader = $submission->registration_role === RegistrationRole::Leader;
        $qrPng = $isTeamOrBundleLeader
            ? null
            : $qrGenerator->pngForSubmission($submission->id);

        try {
            $this->applyOutgoingEmailJitter();

            Mail::to($user->email)->send(
                new RegistrationConfirmationMail($submission, $answersSummary, $qrPng)
            );

            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'recipient_email' => $user->email,
                'status' => EmailLogStatus::Sent,
                'notification_type' => EmailNotificationType::RegistrationSubmitted,
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
                'notification_type' => EmailNotificationType::RegistrationSubmitted,
                'error_message' => $e->getMessage(),
                'sent_at' => null,
            ]);

            Log::error('[SendRegistrationConfirmationJob] Email send failed.', [
                'notification_type' => EmailNotificationType::RegistrationSubmitted->value,
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'recipient_email' => $user->email,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}

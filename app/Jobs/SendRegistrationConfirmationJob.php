<?php

namespace App\Jobs;

use App\Enums\EmailLogStatus;
use App\Mail\RegistrationConfirmationMail;
use App\Models\EmailLog;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRegistrationConfirmationJob implements ShouldQueue
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
                'error_message' => 'User has no email address configured.',
                'sent_at' => null,
            ]);

            Log::warning('[SendRegistrationConfirmationJob] No recipient email.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $answersSummary = $this->buildAnswersSummary($submission);

        try {
            $png = $qrGenerator->pngForSubmission($submission->id);
            Mail::to($user->email)->send(
                new RegistrationConfirmationMail($submission, $png, $answersSummary)
            );

            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'recipient_email' => $user->email,
                'status' => EmailLogStatus::Sent,
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
                'error_message' => $e->getMessage(),
                'sent_at' => null,
            ]);

            Log::error('[SendRegistrationConfirmationJob] Send failed.', [
                'form_answer_id' => $submission->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildAnswersSummary(FormAnswer $submission): array
    {
        $answers = is_array($submission->answers) ? $submission->answers : [];

        $fields = FormField::query()
            ->where('form_id', $submission->form_id)
            ->orderBy('order')
            ->get(['name', 'label', 'input_type']);

        $lines = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field->name, $answers)) {
                continue;
            }
            $value = $answers[$field->name];

            if ($field->input_type === 'fileUpload') {
                $lines[$field->label] = is_string($value) && $value !== ''
                    ? __('File uploaded')
                    : '—';

                continue;
            }

            if (is_array($value)) {
                $lines[$field->label] = implode(', ', array_map(fn ($v) => (string) $v, $value));

                continue;
            }

            if ($value === null || $value === '') {
                $lines[$field->label] = '—';
            } else {
                $lines[$field->label] = (string) $value;
            }
        }

        return $lines;
    }
}

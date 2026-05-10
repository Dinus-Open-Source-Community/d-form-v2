<?php

namespace App\Jobs;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\FormAnswerReviewStatus;
use App\Mail\AttendanceConfirmedMail;
use App\Models\EmailLog;
use App\Models\EventAttendance;
use App\Models\FormAnswer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RecordAttendanceJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public string $eventId,
        public string $formAnswerId,
        public string $scannerUserId,
    ) {
    }

    public function handle(): void
    {
        $submission = FormAnswer::query()
            ->with(['form.event', 'user'])
            ->find($this->formAnswerId);

        if ($submission === null || $submission->form === null || $submission->form->event_id !== $this->eventId) {
            Log::warning('[RecordAttendanceJob] Submission missing or wrong event.', [
                'form_answer_id' => $this->formAnswerId,
                'event_id' => $this->eventId,
            ]);

            return;
        }

        if ($submission->review_status !== FormAnswerReviewStatus::Accepted || $submission->user === null) {
            Log::warning('[RecordAttendanceJob] Submission no longer eligible.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        try {
            $attendance = EventAttendance::query()->create([
                'event_id' => $this->eventId,
                'form_answer_id' => $submission->id,
                'scanned_by_user_id' => $this->scannerUserId,
                'scanned_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                return;
            }

            throw $e;
        }

        $user = $submission->user;
        $event = $submission->form->event;

        if ($user->email === '') {
            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'recipient_email' => '',
                'status' => EmailLogStatus::Failed,
                'notification_type' => EmailNotificationType::AttendanceConfirmed,
                'error_message' => 'User has no email address configured.',
                'sent_at' => null,
            ]);

            Log::warning('[RecordAttendanceJob] No recipient email for attendance confirmation.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        try {
            Mail::to($user->email)->send(new AttendanceConfirmedMail($submission, $attendance));

            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'recipient_email' => $user->email,
                'status' => EmailLogStatus::Sent,
                'notification_type' => EmailNotificationType::AttendanceConfirmed,
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
                'notification_type' => EmailNotificationType::AttendanceConfirmed,
                'error_message' => $e->getMessage(),
                'sent_at' => null,
            ]);

            Log::error('[RecordAttendanceJob] Attendance confirmation mail failed.', [
                'form_answer_id' => $submission->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';

        return $sqlState === '23000';
    }
}

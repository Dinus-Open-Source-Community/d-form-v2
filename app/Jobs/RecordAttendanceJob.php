<?php

namespace App\Jobs;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\FormAnswerReviewStatus;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Mail\AttendanceConfirmedMail;
use App\Models\EmailLog;
use App\Models\EventAttendance;
use App\Services\Registration\FormAnswerRecipientResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RecordAttendanceJob implements ShouldQueue
{
    use AppliesOutgoingEmailDelay;
    use Dispatchable;
    use Queueable;

    public function __construct(
        public string $attendanceId,
    ) {
    }

    public function handle(FormAnswerRecipientResolver $recipientResolver): void
    {
        $attendance = EventAttendance::query()
            ->with(['formAnswer.form.event', 'formAnswer.user'])
            ->find($this->attendanceId);

        if ($attendance === null || $attendance->formAnswer === null || $attendance->formAnswer->form === null) {
            Log::warning('[RecordAttendanceJob] Attendance row missing or incomplete.', [
                'attendance_id' => $this->attendanceId,
            ]);

            return;
        }

        $submission = $attendance->formAnswer;

        if ($submission->review_status !== FormAnswerReviewStatus::Accepted) {
            Log::warning('[RecordAttendanceJob] Submission no longer eligible.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $alreadySent = EmailLog::query()
            ->where('form_answer_id', $submission->id)
            ->where('notification_type', EmailNotificationType::AttendanceConfirmed)
            ->where('status', EmailLogStatus::Sent)
            ->exists();

        if ($alreadySent) {
            Log::info('[RecordAttendanceJob] Confirmation already sent; skipping.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $event = $submission->form->event;
        $recipientEmail = $recipientResolver->email($submission);

        if ($recipientEmail === null || $recipientEmail === '') {
            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $recipientResolver->userIdForLog($submission),
                'recipient_email' => '',
                'status' => EmailLogStatus::Failed,
                'notification_type' => EmailNotificationType::AttendanceConfirmed,
                'error_message' => 'No recipient email address configured.',
                'sent_at' => null,
            ]);

            Log::warning('[RecordAttendanceJob] No recipient email for attendance confirmation.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        try {
            $this->applyOutgoingEmailJitter();

            Mail::to($recipientEmail)->send(new AttendanceConfirmedMail($submission, $attendance));

            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $recipientResolver->userIdForLog($submission),
                'recipient_email' => $recipientEmail,
                'status' => EmailLogStatus::Sent,
                'notification_type' => EmailNotificationType::AttendanceConfirmed,
                'error_message' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $recipientResolver->userIdForLog($submission),
                'recipient_email' => $recipientEmail,
                'status' => EmailLogStatus::Failed,
                'notification_type' => EmailNotificationType::AttendanceConfirmed,
                'error_message' => $e->getMessage(),
                'sent_at' => null,
            ]);

            Log::error('[RecordAttendanceJob] Email send failed.', [
                'notification_type' => EmailNotificationType::AttendanceConfirmed->value,
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'recipient_email' => $recipientEmail,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}

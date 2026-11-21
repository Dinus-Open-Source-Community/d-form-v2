<?php

namespace App\Jobs;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\FormPurpose;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Mail\AttendanceConfirmedMail;
use App\Mail\ClassAttendanceConfirmedMail;
use App\Services\Form\FormSessionSchedule;
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
    use TracksOutgoingEmail;

    public function __construct(
        public string $attendanceId,
    ) {
        $this->initializeEmailDelivery('attendance-confirmed:'.$attendanceId);
    }

    public function handle(FormAnswerRecipientResolver $recipientResolver): void
    {
        $schedule = app(FormSessionSchedule::class);
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

        $isDKelas = $submission->form->purpose() === FormPurpose::DKelas;
        $notificationType = $isDKelas
            ? EmailNotificationType::ClassAttendanceConfirmed
            : EmailNotificationType::AttendanceConfirmed;

        $isSessionAttendance = $attendance->form_session_id !== null;

        if (! $isDKelas && $submission->review_status !== FormAnswerReviewStatus::Accepted) {
            Log::warning('[RecordAttendanceJob] Submission no longer eligible.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $alreadySent = ! $isSessionAttendance && EmailLog::query()
            ->where('form_answer_id', $submission->id)
            ->where('notification_type', $notificationType)
            ->where('status', EmailLogStatus::Sent)
            ->exists();

        if ($alreadySent) {
            Log::info('[RecordAttendanceJob] Confirmation already sent; skipping.', [
                'notification_type' => $notificationType->value,
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $event = $submission->form->event;
        $recipientEmail = $recipientResolver->email($submission);

        $logAttributes = [
            'form_answer_id' => $submission->id,
            'event_id' => $event->id,
            'user_id' => $recipientResolver->userIdForLog($submission),
            'recipient_email' => $recipientEmail ?? '',
            'notification_type' => $notificationType,
        ];

        if ($recipientEmail === null || $recipientEmail === '') {
            $this->markEmailLogUndeliverable($logAttributes, 'No recipient email address configured.');

            Log::warning('[RecordAttendanceJob] No recipient email for attendance confirmation.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $this->applyOutgoingEmailJitter();

        $log = $this->reserveEmailLog($logAttributes);

        if ($log === null) {
            return;
        }

        try {
            if ($isSessionAttendance) {
                $total = $schedule->totalFor($submission->form);
                $recorded = EventAttendance::query()
                    ->where('form_answer_id', $submission->id)
                    ->whereNotNull('form_session_id')
                    ->count();
                $remaining = max(0, $total - $recorded);
                Mail::to($recipientEmail)->send(new ClassAttendanceConfirmedMail($submission, $attendance, $remaining, $total));
            } else {
                Mail::to($recipientEmail)->send(new AttendanceConfirmedMail($submission, $attendance));
            }

            $this->markEmailLogSent($log, $recipientEmail);
        } catch (\Throwable $e) {
            $this->markEmailLogFailed($log, $e);

            Log::error('[RecordAttendanceJob] Email send failed.', [
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

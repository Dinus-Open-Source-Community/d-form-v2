<?php

namespace Tests\Feature\Jobs;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Jobs\RecordAttendanceJob;
use App\Mail\AttendanceConfirmedMail;
use App\Mail\ClassAttendanceConfirmedMail;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormSession;
use App\Models\User;
use App\Services\Registration\FormAnswerRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClassAttendanceMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_session_sends_rendered_class_confirmation_with_counts(): void
    {
        Mail::fake();
        [$event, $form, $submission] = $this->dKelasSubmission();
        $first = FormSession::factory()->create(['form_id' => $form->id, 'session_date' => '2026-12-07']);
        $second = FormSession::factory()->create(['form_id' => $form->id, 'session_date' => '2026-12-14']);

        foreach ([[$first, 1], [$second, 0]] as [$session, $remaining]) {
            $attendance = $this->attendance($event, $submission, $session);
            (new RecordAttendanceJob($attendance->id))->handle(app(FormAnswerRecipientResolver::class));
            Mail::assertSent(ClassAttendanceConfirmedMail::class, function (ClassAttendanceConfirmedMail $mail) use ($attendance, $remaining): bool {
                $html = $mail->render();
                $text = view('mail.class-attendance-confirmed-text', [
                    'event' => $mail->submission->form->event,
                    'form' => $mail->submission->form,
                    'recipientName' => $mail->submission->user?->name ?? 'Peserta',
                    'attendance' => $mail->attendance,
                    'remaining' => $mail->remaining,
                    'total' => $mail->total,
                ])->render();

                return $mail->attendance->id === $attendance->id
                    && $mail->total === 2
                    && $mail->remaining === $remaining
                    && str_contains($html, 'Kehadiran Anda untuk kelas ini telah tercatat.')
                    && str_contains($html, $attendance->formSession->session_date->format('d-m-Y'))
                    && str_contains($html, $remaining.' dari 2')
                    && str_contains($text, 'Tidak ada tindakan lain yang perlu dilakukan.');
            });
        }

        Mail::assertSent(ClassAttendanceConfirmedMail::class, 2);
    }

    public function test_pending_d_kelas_sends_dedicated_type(): void
    {
        Mail::fake();
        [$event, $form, $submission] = $this->dKelasSubmission(['review_status' => 'pending']);
        $session = FormSession::factory()->create(['form_id' => $form->id, 'session_date' => '2026-12-07']);
        $attendance = $this->attendance($event, $submission, $session);

        (new RecordAttendanceJob($attendance->id))->handle(app(FormAnswerRecipientResolver::class));

        Mail::assertSent(ClassAttendanceConfirmedMail::class);
        $this->assertSame(EmailNotificationType::ClassAttendanceConfirmed, EmailLog::query()->where('form_answer_id', $submission->id)->value('notification_type'));
    }

    public function test_missing_recipient_records_failed_safe_log_without_pii(): void
    {
        Mail::fake();
        [$event, $form, $submission] = $this->dKelasSubmission(['user_id' => null, 'invited_email' => null]);
        $session = FormSession::factory()->create(['form_id' => $form->id]);
        $attendance = $this->attendance($event, $submission, $session);

        (new RecordAttendanceJob($attendance->id))->handle(app(FormAnswerRecipientResolver::class));

        Mail::assertNothingSent();
        $this->assertDatabaseHas('event_attendances', ['id' => $attendance->id]);
        $log = EmailLog::query()->where('form_answer_id', $submission->id)->firstOrFail();
        $this->assertSame(EmailNotificationType::ClassAttendanceConfirmed, $log->notification_type);
        $this->assertSame(EmailLogStatus::Failed, $log->status);
        $this->assertSame('', $log->recipient_email);
        $this->assertStringNotContainsString('@', (string) $log->error_message);
    }

    public function test_legacy_accepted_sends_old_mailable_but_pending_is_rejected(): void
    {
        Mail::fake();
        [$event, $form, $accepted] = $this->dKelasSubmission(['review_status' => 'accepted'], 'registration');
        $attendance = $this->attendance($event, $accepted);
        (new RecordAttendanceJob($attendance->id))->handle(app(FormAnswerRecipientResolver::class));
        Mail::assertSent(AttendanceConfirmedMail::class);
        $this->assertSame(EmailNotificationType::AttendanceConfirmed, EmailLog::query()->where('form_answer_id', $accepted->id)->value('notification_type'));

        [$pendingEvent, $pendingForm, $pending] = $this->dKelasSubmission(['review_status' => 'pending'], 'registration');
        $pendingAttendance = $this->attendance($pendingEvent, $pending);
        (new RecordAttendanceJob($pendingAttendance->id))->handle(app(FormAnswerRecipientResolver::class));
        Mail::assertSent(AttendanceConfirmedMail::class, 1);
        $this->assertDatabaseMissing('email_logs', ['form_answer_id' => $pending->id]);
    }

    public function test_session_delivery_keys_are_idempotent_and_distinct_sessions_send_independently(): void
    {
        Mail::fake();
        [$event, $form, $submission] = $this->dKelasSubmission();
        $first = FormSession::factory()->create(['form_id' => $form->id]);
        $second = FormSession::factory()->create(['form_id' => $form->id, 'session_date' => '2026-12-14']);
        $firstAttendance = $this->attendance($event, $submission, $first);
        $firstJob = new RecordAttendanceJob($firstAttendance->id);

        EmailLog::query()->create([
            'form_answer_id' => $submission->id,
            'event_id' => $event->id,
            'recipient_email' => 'existing@example.test',
            'notification_type' => EmailNotificationType::ClassAttendanceConfirmed,
            'status' => EmailLogStatus::Sent,
            'delivery_key' => 'existing-key',
            'sent_at' => now(),
        ]);

        $firstJob->handle(app(FormAnswerRecipientResolver::class));
        $firstJob->handle(app(FormAnswerRecipientResolver::class));

        $firstLogs = EmailLog::query()
            ->where('form_answer_id', $submission->id)
            ->where('delivery_key', $firstJob->deliveryKey)
            ->get();
        $this->assertCount(1, $firstLogs);
        $this->assertSame(EmailLogStatus::Sent, $firstLogs->sole()->status);
        $this->assertSame(2, EmailLog::query()->where('form_answer_id', $submission->id)->where('notification_type', EmailNotificationType::ClassAttendanceConfirmed)->count());

        $secondAttendance = $this->attendance($event, $submission, $second);
        $secondJob = new RecordAttendanceJob($secondAttendance->id);
        $this->assertNotSame($firstJob->deliveryKey, $secondJob->deliveryKey);
        $secondJob->handle(app(FormAnswerRecipientResolver::class));

        $secondLog = EmailLog::query()->where('delivery_key', $secondJob->deliveryKey)->firstOrFail();
        $this->assertSame(EmailLogStatus::Sent, $secondLog->status);
        $this->assertSame($secondJob->deliveryKey, $secondLog->delivery_key);
        $this->assertSame(3, EmailLog::query()->where('form_answer_id', $submission->id)->where('notification_type', EmailNotificationType::ClassAttendanceConfirmed)->count());
        Mail::assertSent(ClassAttendanceConfirmedMail::class, 2);
    }

    /** @return array{Event, Form, FormAnswer} */
    private function dKelasSubmission(array $overrides = [], string $purpose = 'd_kelas'): array
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id, 'metadata' => ['purpose' => $purpose]]);
        $user = User::factory()->create();
        $submission = FormAnswer::factory()->create(array_merge(['form_id' => $form->id, 'user_id' => $user->id, 'review_status' => 'pending'], $overrides));

        return [$event, $form, $submission];
    }

    private function attendance(Event $event, FormAnswer $submission, ?FormSession $session = null): EventAttendance
    {
        return EventAttendance::query()->create(['event_id' => $event->id, 'form_answer_id' => $submission->id, 'form_session_id' => $session?->id, 'attendance_slot' => $session?->id ?? 'default', 'scanned_at' => now()]);
    }
}

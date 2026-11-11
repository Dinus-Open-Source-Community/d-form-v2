<?php

namespace Tests\Feature\Jobs;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Jobs\Broadcasting\SendBroadcastRecipientJob;
use App\Jobs\DeleteDeclinedInvitationMemberSubmissionJob;
use App\Jobs\RecordAttendanceJob;
use App\Jobs\Recruitment\SendRecruitmentApplicationConfirmationJob;
use App\Jobs\Recruitment\SendRecruitmentCorrectionRequestStaffJob;
use App\Jobs\Recruitment\SendRecruitmentInterviewerNotificationJob;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Jobs\SendInviteeInvitationDeclinedNoticeJob;
use App\Jobs\SendRegistrationAcceptedJob;
use App\Jobs\SendRegistrationConfirmationJob;
use App\Jobs\SendRegistrationOtpJob;
use App\Jobs\SendRegistrationRejectedJob;
use App\Jobs\SendTeamInvitationJob;
use App\Jobs\SendTeamInvitationLeaderNoticeJob;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Mail\RegistrationConfirmationMail;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentEmailRenderer;
use App\Services\Recruitment\RecruitmentInterviewVariableBuilder;
use App\Services\Recruitment\RecruitmentQrPngGenerator;
use App\Services\Recruitment\RecruitmentTrackingPortalUrlBuilder;
use App\Services\Registration\FormAnswerRecipientResolver;
use App\Services\Registration\RegistrationAnswersSummarizer;
use App\Services\Registration\RegistrationQrPngGenerator;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Regression coverage for AUDIT-P1-007: retry, idempotency, timeout, and failure logging
 * across the queue jobs that deliver email.
 */
class EmailJobIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
    }

    public function test_every_queue_job_defines_retry_backoff_and_timeout(): void
    {
        $jobs = [
            new SendRegistrationConfirmationJob('id'),
            new SendRegistrationAcceptedJob('id'),
            new SendRegistrationRejectedJob('id'),
            new SendTeamInvitationJob('id'),
            new SendInviteeInvitationDeclinedNoticeJob('id'),
            new SendTeamInvitationLeaderNoticeJob('id', 'accepted'),
            new RecordAttendanceJob('id'),
            new DeleteDeclinedInvitationMemberSubmissionJob('id'),
            new SendRegistrationOtpJob('id', now()->toIso8601String()),
            new SendBroadcastRecipientJob('id'),
            new SendRecruitmentApplicationConfirmationJob('id', 'token'),
            new SendRecruitmentCorrectionRequestStaffJob('id'),
            new SendRecruitmentInterviewerNotificationJob('id'),
            new SendRecruitmentNotificationJob('id', 'final_accepted'),
        ];

        foreach ($jobs as $job) {
            $class = $job::class;

            self::assertSame(3, $job->tries, "{$class} must define tries.");
            self::assertSame(60, $job->timeout, "{$class} must define timeout.");
            self::assertNotEmpty($job->backoff, "{$class} must define backoff.");
        }
    }

    public function test_registration_confirmation_job_sends_once_when_retried(): void
    {
        Mail::fake();

        [$event, $form, $user] = $this->eventFormAndUser();
        $submission = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
        ]);

        $job = new SendRegistrationConfirmationJob($submission->id);

        $job->handle(
            app(RegistrationAnswersSummarizer::class),
            app(RegistrationQrPngGenerator::class),
            app(FormAnswerRecipientResolver::class),
        );
        $job->handle(
            app(RegistrationAnswersSummarizer::class),
            app(RegistrationQrPngGenerator::class),
            app(FormAnswerRecipientResolver::class),
        );

        Mail::assertSent(RegistrationConfirmationMail::class, 1);

        self::assertSame(1, EmailLog::query()
            ->where('form_answer_id', $submission->id)
            ->where('notification_type', EmailNotificationType::RegistrationSubmitted)
            ->where('status', EmailLogStatus::Sent)
            ->count());
    }

    public function test_a_new_dispatch_is_a_new_delivery_so_manual_resend_still_sends(): void
    {
        Mail::fake();

        [$event, $form, $user] = $this->eventFormAndUser();
        $submission = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
        ]);

        foreach ([1, 2] as $attempt) {
            (new SendRegistrationConfirmationJob($submission->id))->handle(
                app(RegistrationAnswersSummarizer::class),
                app(RegistrationQrPngGenerator::class),
                app(FormAnswerRecipientResolver::class),
            );
        }

        Mail::assertSent(RegistrationConfirmationMail::class, 2);

        self::assertSame(2, EmailLog::query()
            ->where('form_answer_id', $submission->id)
            ->where('notification_type', EmailNotificationType::RegistrationSubmitted)
            ->where('status', EmailLogStatus::Sent)
            ->count());
    }

    public function test_interrupted_queued_delivery_is_not_resent_on_retry(): void
    {
        Mail::fake();

        [$event, $form, $user] = $this->eventFormAndUser();
        $submission = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
        ]);

        $job = new SendRegistrationConfirmationJob($submission->id);

        EmailLog::query()->create([
            'form_answer_id' => $submission->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'recipient_email' => $user->email,
            'status' => EmailLogStatus::Queued,
            'notification_type' => EmailNotificationType::RegistrationSubmitted,
            'error_message' => null,
            'sent_at' => null,
            'delivery_key' => $job->deliveryKey,
        ]);

        $job->handle(
            app(RegistrationAnswersSummarizer::class),
            app(RegistrationQrPngGenerator::class),
            app(FormAnswerRecipientResolver::class),
        );

        Mail::assertNothingSent();

        $log = EmailLog::query()->where('delivery_key', $job->deliveryKey)->firstOrFail();

        self::assertSame(EmailLogStatus::Failed, $log->status);
        self::assertStringContainsString('interrupted', (string) $log->error_message);
    }

    public function test_failed_handler_marks_log_failed_without_recipient_pii(): void
    {
        [$event, $form, $user] = $this->eventFormAndUser();
        $submission = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
        ]);

        $job = new SendRegistrationConfirmationJob($submission->id);

        EmailLog::query()->create([
            'form_answer_id' => $submission->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'recipient_email' => 'private-recipient@example.test',
            'status' => EmailLogStatus::Queued,
            'notification_type' => EmailNotificationType::RegistrationSubmitted,
            'error_message' => null,
            'sent_at' => null,
            'delivery_key' => $job->deliveryKey,
        ]);

        Log::spy();

        $job->failed(new RuntimeException('SMTP rejected private-recipient@example.test'));

        $log = EmailLog::query()->where('delivery_key', $job->deliveryKey)->firstOrFail();

        self::assertSame(EmailLogStatus::Failed, $log->status);
        self::assertStringNotContainsString('private-recipient@example.test', (string) $log->error_message);
        self::assertStringContainsString('[message redacted]', (string) $log->error_message);

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context = []): bool => ! str_contains(json_encode($context) ?: '', 'private-recipient@example.test')
                && ($context['job'] ?? null) === SendRegistrationConfirmationJob::class,
        );
    }

    public function test_missing_recipient_creates_a_failed_log_with_the_delivery_key(): void
    {
        [$event, $form] = $this->eventFormAndUser();

        $submission = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => null,
        ]);

        $job = new SendRegistrationConfirmationJob($submission->id);

        $job->handle(
            app(RegistrationAnswersSummarizer::class),
            app(RegistrationQrPngGenerator::class),
            app(FormAnswerRecipientResolver::class),
        );

        $log = EmailLog::query()->where('delivery_key', $job->deliveryKey)->firstOrFail();

        self::assertSame(EmailLogStatus::Failed, $log->status);
        self::assertSame('', $log->recipient_email);
        self::assertStringContainsString('No recipient email', (string) $log->error_message);
    }

    public function test_delivery_key_survives_queue_serialization(): void
    {
        $job = new SendRegistrationConfirmationJob('form-answer-id');
        $restored = unserialize(serialize($job));

        self::assertInstanceOf(SendRegistrationConfirmationJob::class, $restored);
        self::assertSame($job->deliveryKey, $restored->deliveryKey);
    }

    public function test_recruitment_confirmation_job_sends_once_when_retried(): void
    {
        Mail::fake();

        $application = $this->recruitmentApplication();

        $job = new SendRecruitmentApplicationConfirmationJob($application->id, 'tracking-token');

        $job->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentTrackingPortalUrlBuilder::class),
        );
        $job->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentTrackingPortalUrlBuilder::class),
        );

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);

        self::assertSame(1, EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentApplicationSubmitted)
            ->where('status', EmailLogStatus::Sent)
            ->count());
    }

    public function test_recruitment_notification_job_sends_once_when_retried(): void
    {
        Mail::fake();

        $application = $this->recruitmentApplication();

        $job = new SendRecruitmentNotificationJob($application->id, 'final_accepted');

        $handle = fn (): mixed => $job->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentInterviewVariableBuilder::class),
            app(RecruitmentQrPngGenerator::class),
        );

        $handle();
        $handle();

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);

        self::assertSame(1, EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentFinalAccepted)
            ->where('status', EmailLogStatus::Sent)
            ->count());
    }

    public function test_correction_staff_job_skips_recipients_already_delivered_on_retry(): void
    {
        Mail::fake();

        config(['recruitment.staff_notification_email' => 'staff-a@example.test,staff-b@example.test']);

        $application = $this->recruitmentApplication();
        $correction = RecruitmentCorrectionRequest::query()->create([
            'recruitment_application_id' => $application->id,
            'status' => 'pending',
            'request_message' => 'Perbaiki dokumen CV.',
        ]);

        $job = new SendRecruitmentCorrectionRequestStaffJob($correction->id);

        $deliveredKey = $job->deliveryKey.':'.substr(sha1('staff-a@example.test'), 0, 24);

        EmailLog::query()->create([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => 'staff-a@example.test',
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::RecruitmentCorrectionRequestStaff,
            'error_message' => null,
            'sent_at' => now(),
            'delivery_key' => $deliveredKey,
        ]);

        $job->handle(app(RecruitmentEmailRenderer::class));

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);

        self::assertSame(1, EmailLog::query()
            ->where('recipient_email', 'staff-a@example.test')
            ->count());

        self::assertSame(1, EmailLog::query()
            ->where('recipient_email', 'staff-b@example.test')
            ->where('status', EmailLogStatus::Sent)
            ->count());
    }

    /**
     * @return array{0: Event, 1: Form, 2: User}
     */
    private function eventFormAndUser(): array
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        $user = User::factory()->create(['email' => 'participant@example.test']);

        return [$event, $form, $user];
    }

    private function recruitmentApplication(): RecruitmentApplication
    {
        $period = RecruitmentPeriod::factory()->create();
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $division->id,
            'personal_email' => 'applicant@example.test',
        ]);
    }
}

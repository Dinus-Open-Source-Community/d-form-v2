<?php

namespace Tests\Feature\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Exceptions\RedactedMailDeliveryException;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentQrDispatchOutbox;
use App\Models\User;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Services\Recruitment\RecruitmentEmailRenderer;
use App\Services\Recruitment\RecruitmentInterviewVariableBuilder;
use App\Services\Recruitment\RecruitmentQrBulkService;
use App\Services\Recruitment\RecruitmentQrDispatchRecovery;
use App\Services\Recruitment\RecruitmentQrPngGenerator;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RecruitmentQrDispatchRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private RecruitmentPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');
        $this->period = RecruitmentPeriod::factory()->create([
            'created_by' => $this->staff->id,
        ]);
    }

    /**
     * SQLite cannot explain an index plan, so this only locks the index shape the poll relies on.
     * The real plan is asserted on MySQL by
     * {@see test_mysql_pending_poll_plan_is_covering_and_anchor_scans_are_index_backed()}.
     */
    public function test_outbox_pending_poll_has_the_expected_indexes(): void
    {
        self::assertTrue(Schema::hasIndex('recruitment_qr_dispatch_outboxes', ['status', 'available_at']));
        self::assertTrue(Schema::hasIndex('recruitment_qr_dispatch_outboxes', ['status', 'sent_at']));
        self::assertTrue(Schema::hasIndex('recruitment_qr_dispatch_outboxes', ['status', 'failed_at']));
    }

    /**
     * Plan guard for the three outbox reads on MySQL (`RUN_MYSQL_RACE_TESTS=1`, disposable DB).
     * Verified EXPLAIN output, not a hand-written copy of the SQL: the statements come from the
     * query builder used by the service.
     */
    public function test_mysql_pending_poll_plan_is_covering_and_anchor_scans_are_index_backed(): void
    {
        if (! $this->isDisposableMysqlTarget()) {
            $this->markTestSkipped(
                'MySQL plan test requires RUN_MYSQL_RACE_TESTS=1 and a disposable database name.',
            );
        }

        $pendingPlan = $this->explain(RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->limit(100));

        self::assertSame('recruitment_qr_outbox_status_available_idx', $pendingPlan->key);
        self::assertStringContainsString('Using index', (string) $pendingPlan->Extra);
        self::assertStringNotContainsString('filesort', (string) $pendingPlan->Extra);

        foreach ([
            RecruitmentQrDispatchOutbox::STATUS_QUEUED,
            RecruitmentQrDispatchOutbox::STATUS_SENDING,
            RecruitmentQrDispatchOutbox::STATUS_SENT,
            RecruitmentQrDispatchOutbox::STATUS_FAILED,
        ] as $status) {
            $anchorPlan = $this->explain(RecruitmentQrDispatchOutbox::query()
                ->where('status', $status)
                ->orderByDesc('available_at')
                ->limit(1));

            self::assertNotSame('ALL', $anchorPlan->type, "Anchor read for {$status} must not full-scan.");
            self::assertStringNotContainsString('filesort', (string) $anchorPlan->Extra);
        }

        $stalePlan = $this->explain(RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_SENDING)
            ->where('updated_at', '<=', now()->subMinutes(10)));

        self::assertNotSame('ALL', $stalePlan->type, 'Stale-sending count rides the status prefix.');
    }

    public function test_qr_email_log_and_outbox_reservation_roll_back_together(): void
    {
        Queue::fake();
        $application = $this->makeApplicant();
        $outboxWriteFailed = false;

        DB::listen(function (QueryExecuted $query) use (&$outboxWriteFailed): void {
            $sql = strtolower($query->sql);

            if (! $outboxWriteFailed && str_contains($sql, 'insert into')
                && str_contains($sql, 'recruitment_qr_dispatch_outboxes')) {
                $outboxWriteFailed = true;
                throw new RuntimeException('Injected outbox persistence failure.');
            }
        });

        try {
            app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);
            self::fail('Expected the outbox insert to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected outbox persistence failure.', $exception->getMessage());
        }

        self::assertTrue($outboxWriteFailed);
        self::assertSame(0, EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled)
            ->count());
        $this->assertDatabaseCount('recruitment_qr_dispatch_outboxes', 0);
    }

    /**
     * Queue push failure pada request path: intent tetap `pending` untuk recovery poll,
     * sedangkan request tidak menggagalkan seluruh bulk send yang sudah disetujui.
     */
    public function test_committed_qr_reservation_is_recovered_after_queue_dispatch_failure(): void
    {
        Queue::fake();
        $application = $this->makeApplicant();
        $originalDispatcher = app(BusDispatcher::class);
        $failingDispatcher = Mockery::mock(BusDispatcher::class);
        $failingDispatcher->shouldReceive('dispatch')
            ->andThrow(new RuntimeException('private queue connection details'));
        $this->app->instance(BusDispatcher::class, $failingDispatcher);

        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        self::assertSame(['scheduled' => 1, 'skipped' => 0], $result);
        Queue::assertNothingPushed();

        $emailLog = EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled)
            ->firstOrFail();
        self::assertSame(EmailLogStatus::Queued, $emailLog->status);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_PENDING,
        ]);

        $this->app->instance(BusDispatcher::class, $originalDispatcher);
        $this->artisan('recruitment:dispatch-qr-outbox')->assertExitCode(0);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, 1);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
        ]);
    }

    public function test_duplicate_polls_and_duplicate_jobs_deliver_one_qr_email(): void
    {
        Queue::fake();
        Mail::fake();
        $application = $this->makeApplicant();

        app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);
        $emailLog = EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled)
            ->firstOrFail();

        $this->artisan('recruitment:dispatch-qr-outbox')->assertExitCode(0);
        $this->artisan('recruitment:dispatch-qr-outbox')->assertExitCode(0);
        Queue::assertPushed(SendRecruitmentNotificationJob::class, 1);

        $job = new SendRecruitmentNotificationJob(
            applicationId: $application->id,
            templateKey: 'interview_scheduled',
            qrEmailLogId: $emailLog->id,
        );
        $dependencies = [
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentInterviewVariableBuilder::class),
            app(RecruitmentQrPngGenerator::class),
        ];

        $job->handle(...$dependencies);
        $job->handle(...$dependencies);

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);
        self::assertSame(1, EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled)
            ->count());
        $this->assertDatabaseHas('email_logs', [
            'id' => $emailLog->id,
            'status' => EmailLogStatus::Sent->value,
        ]);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_SENT,
        ]);
    }

    public function test_qr_transport_retry_keeps_reservation_queued_until_success(): void
    {
        Queue::fake();
        $application = $this->makeApplicant();
        app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);
        $emailLog = EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled)
            ->firstOrFail();
        $job = new SendRecruitmentNotificationJob(
            applicationId: $application->id,
            templateKey: 'interview_scheduled',
            qrEmailLogId: $emailLog->id,
        );
        $dependencies = [
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentInterviewVariableBuilder::class),
            app(RecruitmentQrPngGenerator::class),
        ];
        $privateTransportFailure = new RuntimeException('SMTP credentials and hidden@example.test');
        $mailManager = app('mail.manager');
        Mail::shouldReceive('to')->once()->andThrow($privateTransportFailure);

        try {
            $job->handle(...$dependencies);
            self::fail('Expected the transport exception to be rethrown safely.');
        } catch (RedactedMailDeliveryException $exception) {
            self::assertStringNotContainsString('hidden@example.test', $exception->getMessage());
        }

        $this->assertDatabaseHas('email_logs', [
            'id' => $emailLog->id,
            'status' => EmailLogStatus::Queued->value,
        ]);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
        ]);

        Mail::swap($mailManager);
        Mail::fake();
        $job->handle(...$dependencies);

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);
        $this->assertDatabaseHas('email_logs', [
            'id' => $emailLog->id,
            'status' => EmailLogStatus::Sent->value,
        ]);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_SENT,
        ]);
    }

    public function test_interrupted_qr_delivery_fails_closed_on_queue_retry(): void
    {
        Queue::fake();
        Mail::fake();
        $application = $this->makeApplicant();
        app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);
        $emailLog = EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled)
            ->firstOrFail();
        $emailLog->forceFill(['delivery_key' => 'recruitment-qr:'.$emailLog->id])->save();
        RecruitmentQrDispatchOutbox::query()
            ->whereKey($emailLog->id)
            ->update(['status' => RecruitmentQrDispatchOutbox::STATUS_SENDING]);

        $job = new SendRecruitmentNotificationJob(
            applicationId: $application->id,
            templateKey: 'interview_scheduled',
            qrEmailLogId: $emailLog->id,
        );
        $queueJob = Mockery::mock(QueueJobContract::class);
        $queueJob->shouldReceive('attempts')->andReturn(2);
        $job->setJob($queueJob);
        $job->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentInterviewVariableBuilder::class),
            app(RecruitmentQrPngGenerator::class),
        );

        Mail::assertNothingSent();
        $this->assertDatabaseHas('email_logs', [
            'id' => $emailLog->id,
            'status' => EmailLogStatus::Failed->value,
            'error_message' => 'Delivery was interrupted before confirmation; not resent to avoid duplicates.',
        ]);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_FAILED,
        ]);
    }

    public function test_qr_delivery_confirmation_failure_is_not_retried_after_transport_acceptance(): void
    {
        Queue::fake();
        Mail::fake();
        $application = $this->makeApplicant();
        app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);
        $emailLog = EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled)
            ->firstOrFail();
        $job = new SendRecruitmentNotificationJob(
            applicationId: $application->id,
            templateKey: 'interview_scheduled',
            qrEmailLogId: $emailLog->id,
        );
        $dependencies = [
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentInterviewVariableBuilder::class),
            app(RecruitmentQrPngGenerator::class),
        ];
        $confirmationWriteFailed = false;

        DB::listen(function (QueryExecuted $query) use (&$confirmationWriteFailed): void {
            if (! $confirmationWriteFailed
                && str_contains(strtolower($query->sql), 'update')
                && str_contains(strtolower($query->sql), 'email_logs')
                && in_array(EmailLogStatus::Sent->value, $query->bindings, true)) {
                $confirmationWriteFailed = true;
                throw new RuntimeException('Injected delivery confirmation failure.');
            }
        });

        try {
            $job->handle(...$dependencies);
            self::fail('Expected the delivery confirmation write to fail.');
        } catch (RedactedMailDeliveryException) {
            self::assertTrue($confirmationWriteFailed);
        }

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);
        $this->assertDatabaseHas('email_logs', [
            'id' => $emailLog->id,
            'status' => EmailLogStatus::Queued->value,
        ]);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_SENDING,
        ]);

        $queueJob = Mockery::mock(QueueJobContract::class);
        $queueJob->shouldReceive('attempts')->andReturn(2);
        $job->setJob($queueJob);
        $job->handle(...$dependencies);

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);
        $this->assertDatabaseHas('email_logs', [
            'id' => $emailLog->id,
            'status' => EmailLogStatus::Failed->value,
            'error_message' => 'Delivery was interrupted before confirmation; not resent to avoid duplicates.',
        ]);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_FAILED,
        ]);
    }

    public function test_terminal_qr_failure_keeps_error_public_safe_and_marks_outbox_failed(): void
    {
        Queue::fake();
        $application = $this->makeApplicant();
        app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);
        $emailLog = EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled)
            ->firstOrFail();
        $job = new SendRecruitmentNotificationJob(
            applicationId: $application->id,
            templateKey: 'interview_scheduled',
            qrEmailLogId: $emailLog->id,
        );

        $job->failed(new RuntimeException('SMTP failure for hidden@example.test'));

        $failedLog = EmailLog::query()->whereKey($emailLog->id)->firstOrFail();
        self::assertSame(EmailLogStatus::Failed, $failedLog->status);
        self::assertStringNotContainsString('hidden@example.test', (string) $failedLog->error_message);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_FAILED,
        ]);
    }

    public function test_recovery_dispatches_only_within_a_bounded_queue_horizon(): void
    {
        Queue::fake();
        config([
            'registration.email_send_delay_seconds' => 7,
            'recruitment.qr_dispatch_horizon_seconds' => 60,
        ]);
        $application = $this->makeApplicant();

        foreach (range(1, 20) as $_sequence) {
            $emailLog = EmailLog::query()->create([
                'recruitment_application_id' => $application->id,
                'event_id' => null,
                'user_id' => null,
                'recipient_email' => (string) $application->personal_email,
                'status' => EmailLogStatus::Queued,
                'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
                'error_message' => null,
                'sent_at' => null,
            ]);

            RecruitmentQrDispatchOutbox::query()->create([
                'email_log_id' => $emailLog->id,
                'status' => RecruitmentQrDispatchOutbox::STATUS_PENDING,
                'available_at' => now(),
                'delay_seconds' => 7,
                'dispatch_attempts' => 0,
            ]);
        }

        $dispatched = app(RecruitmentQrDispatchRecovery::class)->dispatchPending();

        self::assertSame(9, $dispatched);
        Queue::assertPushed(SendRecruitmentNotificationJob::class, 9);
        self::assertSame(9, RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_QUEUED)
            ->count());
        self::assertSame(11, RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_PENDING)
            ->count());
    }

    /**
     * Regresi EP-02: request bulk harus meng-enqueue sendiri setiap penerima yang sudah
     * direservasi. Repo tidak punya scheduler (CONTEXT.md §3), jadi kalau request hanya
     * mengandalkan `dispatchPending()` dengan horizon 60 detik, sisa baris tertahan
     * `pending` selamanya dan hanya ~8-9 email keluar.
     */
    public function test_bulk_send_queues_every_reserved_candidate_without_a_scheduler(): void
    {
        Queue::fake();
        $this->freezeTime();
        config([
            'registration.email_send_delay_seconds' => 7,
            'recruitment.qr_dispatch_horizon_seconds' => 60,
        ]);

        foreach (range(1, 30) as $_sequence) {
            $this->makeApplicant();
        }

        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        self::assertSame(['scheduled' => 30, 'skipped' => 0], $result);
        self::assertSame(30, RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_QUEUED)
            ->count());
        self::assertSame(0, RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_PENDING)
            ->count());

        Queue::assertPushed(SendRecruitmentNotificationJob::class, 30);

        $delays = Queue::pushed(SendRecruitmentNotificationJob::class)
            ->map(fn (SendRecruitmentNotificationJob $job): int => $job->delay->timestamp - now()->timestamp)
            ->values()
            ->all();

        self::assertSame(range(0, 203, 7), $delays, 'Setiap penerima harus mendapat slot berjarak 7 detik.');

        $slots = RecruitmentQrDispatchOutbox::query()
            ->orderBy('available_at')
            ->pluck('available_at')
            ->all();

        for ($index = 1; $index < count($slots); $index++) {
            self::assertSame(7, (int) $slots[$index - 1]->diffInSeconds($slots[$index]));
        }

        self::assertSame(
            now()->timestamp,
            $slots[0]->timestamp,
            'Slot pertama dikirim sekarang, bukan ditunda oleh horizon.',
        );
    }

    public function test_new_qr_dispatch_waits_after_a_recent_completed_send(): void
    {
        Queue::fake();
        config(['registration.email_send_delay_seconds' => 7]);
        $previousApplication = $this->makeApplicant();
        $sentAt = now()->subSeconds(2);
        $previousLog = EmailLog::query()->create([
            'recruitment_application_id' => $previousApplication->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $previousApplication->personal_email,
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => $sentAt,
        ]);
        RecruitmentQrDispatchOutbox::query()->create([
            'email_log_id' => $previousLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_SENT,
            'available_at' => $sentAt,
            'delay_seconds' => 7,
            'dispatch_attempts' => 1,
            'sent_at' => $sentAt,
        ]);
        $application = $this->makeApplicant();

        app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        $newLog = EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled)
            ->firstOrFail();
        $newOutbox = RecruitmentQrDispatchOutbox::query()->whereKey($newLog->id)->firstOrFail();

        self::assertGreaterThanOrEqual(now()->addSeconds(4)->timestamp, $newOutbox->available_at->timestamp);
    }

    public function test_recovery_rebases_overdue_rows_without_relaxing_send_spacing(): void
    {
        Queue::fake();
        config(['registration.email_send_delay_seconds' => 7]);
        foreach (range(1, 3) as $_sequence) {
            $this->makeApplicant();
        }

        $originalDispatcher = app(BusDispatcher::class);
        $failingDispatcher = Mockery::mock(BusDispatcher::class);
        $failingDispatcher->shouldReceive('dispatch')->andThrow(new RuntimeException('Queue unavailable.'));
        $this->app->instance(BusDispatcher::class, $failingDispatcher);

        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        self::assertSame(['scheduled' => 3, 'skipped' => 0], $result);
        Queue::assertNothingPushed();
        self::assertSame(3, RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_PENDING)
            ->count());

        $this->app->instance(BusDispatcher::class, $originalDispatcher);
        $this->travel(2)->minutes();
        $this->artisan('recruitment:dispatch-qr-outbox')->assertExitCode(0);

        $outboxes = RecruitmentQrDispatchOutbox::query()->orderBy('available_at')->get();
        self::assertCount(3, $outboxes);
        $earliestOutbox = RecruitmentQrDispatchOutbox::query()->orderBy('available_at')->firstOrFail();
        self::assertGreaterThanOrEqual(now()->timestamp, $earliestOutbox->available_at->timestamp);

        for ($index = 1; $index < $outboxes->count(); $index++) {
            self::assertGreaterThanOrEqual(
                7,
                $outboxes[$index - 1]->available_at->diffInSeconds($outboxes[$index]->available_at),
            );
        }

        Queue::assertPushed(SendRecruitmentNotificationJob::class, 3);
    }

    /**
     * Recovery poll tidak boleh meng-enqueue ulang baris yang sudah di-queue oleh request path.
     */
    public function test_recovery_poll_does_not_redispatch_intents_the_request_already_queued(): void
    {
        Queue::fake();

        foreach (range(1, 5) as $_sequence) {
            $this->makeApplicant();
        }

        app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);
        Queue::assertPushed(SendRecruitmentNotificationJob::class, 5);

        $this->artisan('recruitment:dispatch-qr-outbox')->assertExitCode(0);
        $this->artisan('recruitment:dispatch-qr-outbox')->assertExitCode(0);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, 5);
        self::assertSame(0, RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_PENDING)
            ->count());
        self::assertSame([1], RecruitmentQrDispatchOutbox::query()
            ->distinct()
            ->pluck('dispatch_attempts')
            ->map(fn (int|string $attempts): int => (int) $attempts)
            ->all());
    }

    /**
     * Poll recovery yang lebih dulu mengklaim intent membuat klaim request path menjadi no-op,
     * sehingga satu intent tidak pernah di-push dua kali.
     */
    public function test_late_request_claim_cannot_duplicate_a_recovery_dispatch(): void
    {
        Queue::fake();
        $application = $this->makeApplicant();
        $emailLog = EmailLog::query()->create([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $application->personal_email,
            'status' => EmailLogStatus::Queued,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => null,
        ]);
        $outbox = RecruitmentQrDispatchOutbox::query()->create([
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_PENDING,
            'available_at' => now(),
            'delay_seconds' => 0,
            'dispatch_attempts' => 0,
        ]);
        $recovery = app(RecruitmentQrDispatchRecovery::class);

        self::assertSame(1, $recovery->dispatchPending());

        // The reservation's own claim arrives after the poller won the row.
        $recovery->enqueueOutbox(
            outbox: $outbox,
            applicationId: (string) $application->id,
            slot: now(),
        );

        Queue::assertPushed(SendRecruitmentNotificationJob::class, 1);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
            'dispatch_attempts' => 1,
        ]);
    }

    /**
     * Baris `sending` yang menggantung tidak boleh dikirim ulang otomatis, tetapi operator harus
     * mendapat sinyalnya dari command dispatch.
     */
    public function test_stale_sending_intent_is_reported_and_never_resent(): void
    {
        Queue::fake();
        $application = $this->makeApplicant();
        $emailLog = EmailLog::query()->create([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $application->personal_email,
            'status' => EmailLogStatus::Queued,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => null,
        ]);
        RecruitmentQrDispatchOutbox::query()->create([
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_SENDING,
            'available_at' => now()->subMinutes(30),
            'delay_seconds' => 0,
            'dispatch_attempts' => 1,
        ]);
        RecruitmentQrDispatchOutbox::query()
            ->whereKey($emailLog->id)
            ->update(['updated_at' => now()->subMinutes(30)]);
        Log::spy();

        $this->artisan('recruitment:dispatch-qr-outbox')
            ->expectsOutputToContain('Dispatched 0 recruitment QR notification(s).')
            ->expectsOutputToContain(
                "1 QR intent(s) stuck in sending; reconcile with "
                ."'recruitment:reconcile-qr-outbox {emailLogId} {sent|failed}'."
            )
            ->assertExitCode(0);

        Queue::assertNothingPushed();
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('recruitment_qr_outbox_stale_sending', ['count' => 1]);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_SENDING,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'id' => $emailLog->id,
            'status' => EmailLogStatus::Queued->value,
        ]);
    }

    /** Kirim ulang dengan `include_sent` mereservasi intent baru dan langsung meng-enqueue-nya. */
    public function test_include_sent_resend_reserves_and_queues_a_fresh_intent(): void
    {
        Queue::fake();
        $application = $this->makeApplicant();
        $deliveredAt = now()->subMinute();
        $deliveredLog = EmailLog::query()->create([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $application->personal_email,
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => $deliveredAt,
        ]);
        RecruitmentQrDispatchOutbox::query()->create([
            'email_log_id' => $deliveredLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_SENT,
            'available_at' => $deliveredAt,
            'delay_seconds' => 7,
            'dispatch_attempts' => 1,
            'sent_at' => $deliveredAt,
        ]);

        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period, includeSent: true);

        self::assertSame(['scheduled' => 1, 'skipped' => 0], $result);
        self::assertSame(2, EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled)
            ->count());
        self::assertSame(1, RecruitmentQrDispatchOutbox::query()
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_QUEUED)
            ->count());
        Queue::assertPushed(SendRecruitmentNotificationJob::class, 1);
    }

    /**
     * Benchmark biaya bulk send: jumlah query dan memori harus tumbuh linear terhadap jumlah
     * penerima, bukan kuadratik. Angka aktual dicatat di laporan EP-02 lane D.
     */
    public function test_bulk_send_cost_grows_linearly_with_recipient_count(): void
    {
        Queue::fake();
        config(['registration.email_send_delay_seconds' => 7]);

        $measure = function (int $recipients): array {
            $period = RecruitmentPeriod::factory()->create(['created_by' => $this->staff->id]);
            $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

            foreach (range(1, $recipients) as $_sequence) {
                RecruitmentApplication::factory()->create([
                    'recruitment_period_id' => $period->id,
                    'primary_division_id' => $division->id,
                    'stage' => ApplicationStage::Interview,
                    'result' => ApplicationResult::Pending,
                ]);
            }

            $queries = 0;
            DB::listen(function () use (&$queries): void {
                $queries++;
            });

            if (function_exists('memory_reset_peak_usage')) {
                memory_reset_peak_usage();
            }

            // `false` = emalloc usage (the `true` variant is the mapped arena and stays flat for
            // small allocations, which made this guard pass vacuously).
            $baseline = memory_get_usage(false);
            $startedAt = microtime(true);

            app(RecruitmentQrBulkService::class)->send($this->staff, $period);

            $elapsed = microtime(true) - $startedAt;
            $bytes = max(0, memory_get_peak_usage(false) - $baseline);

            return ['queries' => $queries, 'bytes' => $bytes, 'seconds' => $elapsed];
        };

        $small = $measure(25);
        $large = $measure(100);

        fwrite(STDERR, sprintf(
            "\n[qr-bulk-cost] 25: %d queries, %.1f MB peak, %.2fs | 100: %d queries, %.1f MB peak, %.2fs\n",
            $small['queries'],
            $small['bytes'] / 1048576,
            $small['seconds'],
            $large['queries'],
            $large['bytes'] / 1048576,
            $large['seconds'],
        ));

        $queriesPerRecipient = $large['queries'] / 100;
        $bytesPerRecipient = $large['bytes'] / 100;

        self::assertLessThanOrEqual(
            9,
            $queriesPerRecipient,
            'Biaya query per penerima harus konstan (tanpa N+1 tambahan).',
        );
        self::assertGreaterThan(
            0,
            $large['bytes'],
            'Pengukuran memori harus benar-benar terukur (bukan 0 karena peak arena).',
        );
        self::assertLessThan(
            262144,
            $bytesPerRecipient,
            'Memori per penerima harus tetap kecil; materialisasi penuh O(N) perlu benchmark sebelum diubah.',
        );
    }


    /**
     * True only for the opt-in race/plan suite against a disposable MySQL database.
     * The suite is driven by the committed `phpunit.race.xml`.
     */
    private function isDisposableMysqlTarget(): bool
    {
        $optIn = $_SERVER['RUN_MYSQL_RACE_TESTS'] ?? getenv('RUN_MYSQL_RACE_TESTS');

        if ($optIn !== '1' || DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        $database = (string) DB::connection()->getDatabaseName();

        if (preg_match('/\A[a-z0-9]+(?:_[a-z0-9]+)*_(?:test|testing|e2e)\z/i', $database) !== 1) {
            return false;
        }

        return Schema::hasTable('recruitment_qr_dispatch_outboxes');
    }


    /**
     * H2: sebuah job yang hilang (Redis flush/`queue:clear`) meninggalkan baris `queued` tanpa
     * jejak. Command harus melaporkannya dan meng-enqueue ulang, dan re-push tidak boleh
     * menggandakan email walaupun job asli akhirnya muncul.
     */
    public function test_lost_queued_job_is_reported_requeued_and_cannot_double_send(): void
    {
        Queue::fake();
        Mail::fake();
        $application = $this->makeApplicant();
        $emailLog = EmailLog::query()->create([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $application->personal_email,
            'status' => EmailLogStatus::Queued,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => null,
        ]);
        RecruitmentQrDispatchOutbox::query()->create([
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
            'available_at' => now()->subMinutes(30),
            'delay_seconds' => 7,
            'dispatch_attempts' => 1,
        ]);
        Log::spy();

        $this->artisan('recruitment:dispatch-qr-outbox')
            ->expectsOutputToContain('1 QR intent(s) queued past the grace window; their queue job was lost.')
            ->expectsOutputToContain('Re-queued 1 lost recruitment QR notification(s).')
            ->assertExitCode(0);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, 1);
        Log::shouldHaveReceived('warning')->with('recruitment_qr_outbox_stale_queued', ['count' => 1]);

        $outbox = RecruitmentQrDispatchOutbox::query()->whereKey($emailLog->id)->firstOrFail();
        self::assertSame(RecruitmentQrDispatchOutbox::STATUS_QUEUED, $outbox->status);
        self::assertSame(2, $outbox->dispatch_attempts);
        self::assertGreaterThanOrEqual(now()->timestamp, $outbox->available_at->timestamp);

        // The lease means a row is re-queued once per grace window, not on every poll.
        $this->artisan('recruitment:dispatch-qr-outbox')->assertExitCode(0);
        Queue::assertPushed(SendRecruitmentNotificationJob::class, 1);

        // Both the re-pushed job and the original (lost) job may run: the claim admits one send.
        $replacement = Queue::pushed(SendRecruitmentNotificationJob::class)->first();
        $dependencies = [
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentInterviewVariableBuilder::class),
            app(RecruitmentQrPngGenerator::class),
        ];
        $replacement->handle(...$dependencies);

        $originalJob = new SendRecruitmentNotificationJob(
            applicationId: $application->id,
            templateKey: 'interview_scheduled',
            qrEmailLogId: $emailLog->id,
        );
        $originalJob->handle(...$dependencies);

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_SENT,
        ]);
    }

    /**
     * M1: dua bulk send (periode berbeda) tidak boleh berbagi slot pertama. Pointer slot
     * di-cache di bawah lock, jadi panggilan kedua melanjutkan setelah slot terakhir.
     */
    public function test_concurrent_bulk_sends_cannot_share_a_send_slot(): void
    {
        Queue::fake();
        $this->freezeTime();
        config(['registration.email_send_delay_seconds' => 7]);

        $otherPeriod = RecruitmentPeriod::factory()->create(['created_by' => $this->staff->id]);
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);
        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $otherPeriod->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $service = app(RecruitmentQrBulkService::class);
        $service->send($this->staff, $this->period);
        $service->send($this->staff, $otherPeriod);

        $slots = RecruitmentQrDispatchOutbox::query()
            ->orderBy('available_at')
            ->pluck('available_at')
            ->all();

        self::assertCount(2, $slots);
        self::assertSame(
            7,
            (int) $slots[0]->diffInSeconds($slots[1]),
            'Dua bulk send tidak boleh berbagi slot; slot kedua harus 7 detik setelah yang pertama.',
        );
    }

    /** M1: pointer cache dihormati walaupun DB anchor kosong (mis. setelah cache dibersihkan). */
    public function test_cached_slot_pointer_sets_a_floor_for_a_new_batch(): void
    {
        Queue::fake();
        $this->freezeTime();
        config(['registration.email_send_delay_seconds' => 7]);
        // Key internal dari RecruitmentQrDispatchRecovery::SLOT_POINTER_KEY.
        Cache::put('recruitment-qr-slot-pointer', now()->addMinutes(5)->timestamp, now()->addHour());

        $this->makeApplicant();
        app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        $slot = RecruitmentQrDispatchOutbox::query()->value('available_at');
        self::assertGreaterThanOrEqual(now()->addMinutes(5)->addSeconds(7)->timestamp, $slot->timestamp);
    }

    /** M1: lock yang gagal diambil hanya melemahkan spacing, bukan menggagalkan bulk send. */
    public function test_slot_lock_timeout_degrades_to_the_database_anchor(): void
    {
        Queue::fake();
        config(['registration.email_send_delay_seconds' => 7]);
        $this->makeApplicant();
        $heldLock = Cache::lock('recruitment-qr-slot-lock', 10);
        self::assertTrue($heldLock->get());
        Log::spy();

        try {
            $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);
        } finally {
            $heldLock->release();
        }

        self::assertSame(['scheduled' => 1, 'skipped' => 0], $result);
        Queue::assertPushed(SendRecruitmentNotificationJob::class, 1);
        Log::shouldHaveReceived('warning')
            ->with('recruitment_qr_slot_lock_timeout', Mockery::type('array'));
    }

    /**
     * M5: recovery oportunistik dari page load dibatasi cache lock, sehingga dua request
     * bersamaan tidak mem-poll dua kali dan tidak membanjiri queue.
     */
    public function test_opportunistic_recovery_is_guarded_by_a_cache_lock(): void
    {
        Queue::fake();
        $application = $this->makeApplicant();
        $emailLog = EmailLog::query()->create([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $application->personal_email,
            'status' => EmailLogStatus::Queued,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => null,
        ]);
        RecruitmentQrDispatchOutbox::query()->create([
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_PENDING,
            'available_at' => now(),
            'delay_seconds' => 0,
            'dispatch_attempts' => 0,
        ]);
        $recovery = app(RecruitmentQrDispatchRecovery::class);
        $heldLock = Cache::lock('recruitment-qr-recovery', 60);
        self::assertTrue($heldLock->get());

        try {
            self::assertSame(0, $recovery->recoverOpportunistically());
            Queue::assertNothingPushed();
        } finally {
            $heldLock->release();
        }

        self::assertSame(1, $recovery->recoverOpportunistically());
        Queue::assertPushed(SendRecruitmentNotificationJob::class, 1);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
        ]);
    }

    /** M5: halaman QR (tab peserta) menjalankan recovery oportunistik untuk intent yang tertinggal. */
    public function test_qr_page_load_drives_the_recovery_net_without_a_scheduler(): void
    {
        Queue::fake();
        $application = $this->makeApplicant();
        $emailLog = EmailLog::query()->create([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $application->personal_email,
            'status' => EmailLogStatus::Queued,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => null,
        ]);
        RecruitmentQrDispatchOutbox::query()->create([
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_PENDING,
            'available_at' => now(),
            'delay_seconds' => 0,
            'dispatch_attempts' => 0,
        ]);

        $this->actingAs($this->staff)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'peserta']))
            ->assertOk();

        Queue::assertPushed(SendRecruitmentNotificationJob::class, 1);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
        ]);
    }

    /** @param Builder<RecruitmentQrDispatchOutbox> $query */
    private function explain(Builder $query): object
    {
        return DB::selectOne('EXPLAIN '.$query->toSql(), $query->getBindings());
    }

    private function makeApplicant(): RecruitmentApplication
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);
    }
}

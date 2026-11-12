<?php

namespace Tests\Feature\Recruitment;

use App\Jobs\Recruitment\SendRecruitmentApplicationConfirmationJob;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Exceptions\RedactedMailDeliveryException;
use App\Services\Recruitment\RecruitmentEmailRenderer;
use App\Services\Recruitment\RecruitmentTrackingAuthenticator;
use App\Services\Recruitment\RecruitmentTrackingPortalUrlBuilder;
use App\Services\Recruitment\RecruitmentTrackingResendService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RecruitmentTrackingResendTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private RecruitmentApplication $application;

    private string $originalToken = 'AB12CD34';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

        $this->application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'personal_email' => 'applicant@example.com',
            'tracking_token_hash' => Hash::make($this->originalToken),
        ]);
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo([
            'recruitment.periods.view',
            'recruitment.applications.list',
            'recruitment.applications.view',
            'recruitment.screening.decide',
        ]);

        return $user;
    }

    public function test_staff_can_resend_tracking_and_rotates_token_after_delivery(): void
    {
        Queue::fake();
        Mail::fake();

        $this->actingAs($this->staff())
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application))
            ->assertRedirect()
            ->assertSessionHas('message');

        $this->application->refresh();
        $this->assertTrue(Hash::check($this->originalToken, (string) $this->application->tracking_token_hash));

        $queuedJob = null;
        Queue::assertPushed(SendRecruitmentApplicationConfirmationJob::class, function (SendRecruitmentApplicationConfirmationJob $job) use (&$queuedJob): bool {
            $queuedJob = $job;

            return $job->applicationId === $this->application->id;
        });

        if (! $queuedJob instanceof SendRecruitmentApplicationConfirmationJob) {
            self::fail('Expected a tracking confirmation job to be queued.');
        }

        $queuedJob->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentTrackingPortalUrlBuilder::class),
        );

        $this->application->refresh();
        $this->assertFalse(Hash::check($this->originalToken, (string) $this->application->tracking_token_hash));
        $this->assertTrue(Hash::check($queuedJob->trackingToken, (string) $this->application->tracking_token_hash));

        $this->assertDatabaseHas('recruitment_activity_logs', [
            'recruitment_application_id' => $this->application->id,
            'action' => 'tracking.resend',
        ]);
    }

    public function test_new_token_authenticates_only_after_resend_delivery_succeeds(): void
    {
        Queue::fake();
        Mail::fake();

        $this->actingAs($this->staff())
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application));

        $authenticator = app(RecruitmentTrackingAuthenticator::class);
        $this->assertNotNull(
            $authenticator->attempt($this->application->registration_number, $this->originalToken),
        );

        $queuedJob = null;
        Queue::assertPushed(SendRecruitmentApplicationConfirmationJob::class, function (SendRecruitmentApplicationConfirmationJob $job) use (&$queuedJob): bool {
            $queuedJob = $job;

            return $job->applicationId === $this->application->id;
        });

        if (! $queuedJob instanceof SendRecruitmentApplicationConfirmationJob) {
            self::fail('Expected a tracking confirmation job to be queued.');
        }

        $this->assertNull(
            $authenticator->attempt($this->application->registration_number, $queuedJob->trackingToken),
        );

        $queuedJob->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentTrackingPortalUrlBuilder::class),
        );

        $application = $authenticator->attempt(
            $this->application->registration_number,
            $queuedJob->trackingToken,
        );

        $this->assertSame($this->application->id, $application?->id);
        $this->assertNull(
            $authenticator->attempt($this->application->registration_number, $this->originalToken),
        );
    }

    public function test_stale_resend_job_cannot_replace_token_from_newer_request(): void
    {
        Queue::fake();
        Mail::fake();

        $staff = $this->staff();
        $this->actingAs($staff)
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application))
            ->assertRedirect();
        $this->post(route('dashboard.recruitment.applications.resend-email', $this->application), [
            'type' => 'confirmation',
        ])->assertRedirect();

        $jobs = [];
        Queue::assertPushed(SendRecruitmentApplicationConfirmationJob::class, function (SendRecruitmentApplicationConfirmationJob $job) use (&$jobs): bool {
            $jobs[] = $job;

            return $job->applicationId === $this->application->id;
        });

        $this->assertCount(2, $jobs);

        $jobs[1]->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentTrackingPortalUrlBuilder::class),
        );
        $this->assertTrue(Hash::check($jobs[1]->trackingToken, (string) $this->application->fresh()->tracking_token_hash));

        $jobs[0]->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentTrackingPortalUrlBuilder::class),
        );

        $this->assertTrue(Hash::check($jobs[1]->trackingToken, (string) $this->application->fresh()->tracking_token_hash));
        $this->assertFalse(Hash::check($jobs[0]->trackingToken, (string) $this->application->fresh()->tracking_token_hash));
        Mail::assertSentCount(1);
    }

    public function test_tracking_resend_limit_is_shared_with_generic_email_endpoint(): void
    {
        Queue::fake();
        Mail::fake();

        $this->actingAs($this->staff());

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post(route('dashboard.recruitment.applications.resend-tracking', $this->application))
                ->assertRedirect();
        }

        $this->post(route('dashboard.recruitment.applications.resend-email', $this->application), [
            'type' => 'tracking',
        ])->assertRedirect();

        $this->post(route('dashboard.recruitment.applications.resend-tracking', $this->application))
            ->assertStatus(429);
        $this->post(route('dashboard.recruitment.applications.resend-email', $this->application), [
            'type' => 'tracking',
        ])->assertStatus(429);
    }

    public function test_tracking_resend_limit_counts_existing_tracking_activity_logs(): void
    {
        $staff = $this->staff();
        $activityLogger = app(\App\Services\Recruitment\RecruitmentActivityLogger::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $activityLogger->log(
                action: 'tracking.resend',
                actor: $staff,
                application: $this->application,
            );
        }

        Queue::fake();
        $this->actingAs($staff)
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application))
            ->assertStatus(429);

        Queue::assertNothingPushed();
    }

    public function test_previous_token_remains_usable_when_resend_dispatch_fails(): void
    {
        Bus::shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('Synthetic queue dispatch failure.'));

        try {
            app(RecruitmentTrackingResendService::class)->resend($this->staff(), $this->application);
            self::fail('A queue dispatch failure should be rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('Synthetic queue dispatch failure.', $exception->getMessage());
        }

        $application = app(RecruitmentTrackingAuthenticator::class)->attempt(
            $this->application->registration_number,
            $this->originalToken,
        );

        $this->assertSame($this->application->id, $application?->id);
    }

    public function test_failed_new_resend_does_not_make_an_existing_queued_job_stale(): void
    {
        Queue::fake();
        Mail::fake();

        $staff = $this->staff();
        $resendService = app(RecruitmentTrackingResendService::class);
        $resendService->resend($staff, $this->application);

        $queuedJob = null;
        Queue::assertPushed(SendRecruitmentApplicationConfirmationJob::class, function (SendRecruitmentApplicationConfirmationJob $job) use (&$queuedJob): bool {
            $queuedJob = $job;

            return $job->applicationId === $this->application->id;
        });

        if (! $queuedJob instanceof SendRecruitmentApplicationConfirmationJob) {
            self::fail('Expected the first tracking confirmation job to be queued.');
        }

        Bus::shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('Synthetic queue dispatch failure.'));

        try {
            $resendService->resend($staff, $this->application);
            self::fail('The second queue dispatch should fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Synthetic queue dispatch failure.', $exception->getMessage());
        }

        $queuedJob->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentTrackingPortalUrlBuilder::class),
        );

        $this->assertTrue(Hash::check($queuedJob->trackingToken, (string) $this->application->fresh()->tracking_token_hash));
        Mail::assertSentCount(1);
    }

    public function test_previous_token_remains_usable_when_resend_email_fails(): void
    {
        Queue::fake();

        $this->actingAs($this->staff())
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application))
            ->assertRedirect();

        $queuedJob = null;
        Queue::assertPushed(SendRecruitmentApplicationConfirmationJob::class, function (SendRecruitmentApplicationConfirmationJob $job) use (&$queuedJob): bool {
            $queuedJob = $job;

            return $job->applicationId === $this->application->id;
        });

        if (! $queuedJob instanceof SendRecruitmentApplicationConfirmationJob) {
            self::fail('Expected a tracking confirmation job to be queued.');
        }

        Mail::shouldReceive('to->send')
            ->once()
            ->andThrow(new RuntimeException('Synthetic mail transport failure.'));

        try {
            $queuedJob->handle(
                app(RecruitmentEmailRenderer::class),
                app(RecruitmentTrackingPortalUrlBuilder::class),
            );
            self::fail('A mail transport failure should be rethrown.');
        } catch (RedactedMailDeliveryException) {
            // Queue retry preserves the pending delivery while the previous token stays active.
        }

        $authenticator = app(RecruitmentTrackingAuthenticator::class);
        $this->assertNotNull(
            $authenticator->attempt($this->application->registration_number, $this->originalToken),
        );
        $this->assertNull(
            $authenticator->attempt($this->application->registration_number, $queuedJob->trackingToken),
        );
    }

    public function test_user_without_permission_cannot_resend_tracking(): void
    {
        Queue::fake();

        $viewer = User::factory()->create();
        $viewer->assignRole('recruitment-interviewer');
        $viewer->givePermissionTo(['recruitment.applications.view', 'recruitment.periods.view']);

        $this->actingAs($viewer)
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application))
            ->assertForbidden();

        Queue::assertNothingPushed();

        $this->assertTrue(Hash::check($this->originalToken, (string) $this->application->fresh()->tracking_token_hash));
    }

    public function test_resend_logs_activity_without_recipient_email(): void
    {
        Queue::fake();

        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application));

        /** @var RecruitmentActivityLog $log */
        $log = RecruitmentActivityLog::query()
            ->where('recruitment_application_id', $this->application->id)
            ->where('action', 'tracking.resend')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->actor_id);
        $this->assertArrayNotHasKey('recipient_email', $log->new_values ?? []);
    }

    /**
     * Queue redelivery setelah email benar-benar terkirim tidak boleh membatalkan token yang
     * sudah aktif: retry hanya menemukan EmailLog `sent` dan berhenti tanpa menulis hash.
     */
    public function test_redelivered_resend_job_keeps_the_activated_token(): void
    {
        Queue::fake();
        Mail::fake();

        $this->actingAs($this->staff())
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application));

        $queuedJob = null;
        Queue::assertPushed(SendRecruitmentApplicationConfirmationJob::class, function (SendRecruitmentApplicationConfirmationJob $job) use (&$queuedJob): bool {
            $queuedJob = $job;

            return $job->applicationId === $this->application->id;
        });

        if (! $queuedJob instanceof SendRecruitmentApplicationConfirmationJob) {
            self::fail('Expected a tracking confirmation job to be queued.');
        }

        $queuedJob->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentTrackingPortalUrlBuilder::class),
        );

        $activatedHash = (string) $this->application->fresh()->tracking_token_hash;
        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);

        // Queue redelivery of the same job: the delivery is already sent, so it must not resend
        // and must not rewrite the activated token.
        $queuedJob->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentTrackingPortalUrlBuilder::class),
        );

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);
        $application = $this->application->fresh();
        self::assertSame($activatedHash, (string) $application->tracking_token_hash);
        self::assertTrue(Hash::check($queuedJob->trackingToken, (string) $application->tracking_token_hash));
    }

    /**
     * MySQL DDL tidak transaksional: kalau kolom sudah ada (crash setelah ALTER sebelum migrasi
     * tercatat), migrasi ulang harus no-op, bukan gagal "Duplicate column".
     */
    public function test_tracking_token_generation_migration_is_idempotent(): void
    {
        self::assertTrue(Schema::hasColumn('recruitment_applications', 'tracking_token_generation'));

        /** @var object{up: callable} $migration */
        $migration = require database_path(
            'migrations/2026_11_10_000006_add_tracking_token_generation_to_recruitment_applications_table.php'
        );
        $migration->up();

        self::assertTrue(Schema::hasColumn('recruitment_applications', 'tracking_token_generation'));
    }
}

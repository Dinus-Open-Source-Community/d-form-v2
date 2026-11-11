<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DeleteDeclinedInvitationMemberSubmissionJob;
use App\Jobs\Recruitment\SendRecruitmentApplicationConfirmationJob;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Jobs\SendInviteeInvitationDeclinedNoticeJob;
use App\Jobs\SendTeamInvitationLeaderNoticeJob;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Support\RedactedDatabaseUuidFailedJobProvider;
use App\Support\RedactedFailedJobProvider;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\Failed\PrunableFailedJobProvider;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class GenericExceptionFailingJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        throw new RuntimeException(
            'Could not send to synthetic.person@example.test; applicant=Synthetic Person; nim=EP02-NIM-482915; phone=+62 812-3456-7890; password=synthetic-password token=synthetic-token',
        );
    }
}

class SafeExceptionReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_exception_report_redacts_message_and_stops_default_logging(): void
    {
        Log::spy();

        $correlationId = (string) Str::uuid();
        $this->app->make('request')->headers->set('X-Request-ID', $correlationId);

        app(ExceptionHandler::class)->report(new RuntimeException(
            'Could not send to synthetic.person@example.test; applicant=Synthetic Person; nim=EP02-NIM-482915; phone=+62 812-3456-7890; password=synthetic-password token=synthetic-token',
        ));

        Log::shouldHaveReceived('error')->once()->withArgs(
            static function (string $event, array $context) use ($correlationId): bool {
                return $event === 'unhandled_exception'
                    && ($context['exception_class'] ?? null) === RuntimeException::class
                    && ($context['message'] ?? null) === RuntimeException::class.': [message redacted]'
                    && ($context['correlation_id'] ?? null) === $correlationId
                    && array_keys($context) === ['exception_class', 'message', 'file', 'line', 'frames', 'correlation_id'];
            },
        );
    }

    public function test_global_report_keeps_location_and_frames_without_arguments_or_pii(): void
    {
        Log::spy();

        $exception = $this->failWithSensitiveArguments(
            'synthetic.person@example.test',
            'synthetic-token-canary',
            'synthetic-password-canary',
        );

        app(ExceptionHandler::class)->report($exception);

        Log::shouldHaveReceived('error')->once()->withArgs(
            static function (string $event, array $context): bool {
                $serialized = json_encode($context, JSON_THROW_ON_ERROR);
                $frames = $context['frames'] ?? [];

                return $event === 'unhandled_exception'
                    && ($context['exception_class'] ?? null) === RuntimeException::class
                    && ($context['message'] ?? null) === RuntimeException::class.': [message redacted]'
                    && ($context['file'] ?? null) === __FILE__
                    && is_int($context['line'] ?? null)
                    && is_array($frames)
                    && $frames !== []
                    && count($frames) <= 10
                    && str_contains(implode("\n", $frames), 'failWithSensitiveArguments')
                    && ! str_contains($serialized, 'synthetic.person@example.test')
                    && ! str_contains($serialized, 'synthetic-token-canary')
                    && ! str_contains($serialized, 'synthetic-password-canary')
                    && ! str_contains($serialized, 'Object(')
                    && ! str_contains($serialized, 'Array(');
            },
        );
    }

    /**
     * Build an exception from a frame that carries sensitive arguments, so a reporter that
     * serialized trace arguments would leak them.
     */
    private function failWithSensitiveArguments(string $email, string $token, string $password): RuntimeException
    {
        return new RuntimeException("delivery failed for {$email} token={$token} password={$password}");
    }

    public function test_non_uuid_failed_job_provider_receives_a_redacted_exception(): void
    {
        $provider = \Mockery::mock(FailedJobProviderInterface::class);
        $provider->shouldReceive('log')
            ->once()
            ->withArgs(static function (mixed $connection, mixed $queue, mixed $payload, mixed $exception): bool {
                return $connection === 'file'
                    && $queue === 'default'
                    && $payload === '{"uuid":"failed-id"}'
                    && $exception instanceof RuntimeException
                    && str_contains($exception->getMessage(), '[message redacted]')
                    && ! str_contains($exception->getMessage(), 'private-recipient@example.test');
            })
            ->andReturn('failed-id');

        $failer = new RedactedFailedJobProvider($provider);

        self::assertSame(
            'failed-id',
            $failer->log(
                'file',
                'default',
                '{"uuid":"failed-id"}',
                new RuntimeException('Failed for private-recipient@example.test with password=private-value'),
            ),
        );
    }

    public function test_non_uuid_prunable_failed_job_provider_keeps_pruning_support(): void
    {
        config([
            'queue.failed.driver' => 'file',
            'queue.failed.path' => storage_path('framework/testing/failed-jobs.json'),
        ]);
        $this->app->forgetInstance('queue.failer');

        $provider = app('queue.failer');

        self::assertInstanceOf(PrunableFailedJobProvider::class, $provider);
        $this->artisan('queue:prune-failed', ['--hours' => 168])
            ->assertSuccessful();
    }

    public function test_generic_failed_queue_job_is_redacted_before_database_write_and_in_logs(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'sqlite',
            'queue.connections.database.table' => 'jobs',
            'queue.connections.database.queue' => 'default',
            'queue.failed.driver' => 'database-uuids',
            'queue.failed.database' => 'sqlite',
            'queue.failed.table' => 'failed_jobs',
        ]);
        self::assertInstanceOf(RedactedDatabaseUuidFailedJobProvider::class, app('queue.failer'));

        Log::spy();
        Queue::connection('database')->push(new GenericExceptionFailingJob());

        $queuedPayload = (string) DB::table('jobs')->value('payload');
        self::assertStringNotContainsString('synthetic.person@example.test', $queuedPayload);
        self::assertStringNotContainsString('synthetic-password', $queuedPayload);
        self::assertStringNotContainsString('synthetic-token', $queuedPayload);

        $this->artisan('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
            '--sleep' => 0,
        ])->assertSuccessful();

        $failedJob = DB::table('failed_jobs')->first();
        self::assertNotNull($failedJob);
        self::assertTrue(Str::isUuid($failedJob->uuid));
        self::assertSame('database', $failedJob->connection);
        self::assertSame('default', $failedJob->queue);
        // Redaction must touch only the exception column: the stored payload is byte-identical
        // to what was queued, so the record can still be retried and inspected.
        self::assertSame($queuedPayload, $failedJob->payload);
        $failedPayload = json_decode($failedJob->payload, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($failedJob->uuid, $failedPayload['uuid']);
        self::assertSame(RuntimeException::class.': [message redacted]', $failedJob->exception);
        self::assertStringNotContainsString('synthetic.person@example.test', $failedJob->exception);
        self::assertStringNotContainsString('Synthetic Person', $failedJob->exception);
        self::assertStringNotContainsString('EP02-NIM-482915', $failedJob->exception);
        self::assertStringNotContainsString('+62 812-3456-7890', $failedJob->exception);
        self::assertStringNotContainsString('synthetic-password', $failedJob->exception);
        self::assertStringNotContainsString('synthetic-token', $failedJob->exception);
        self::assertSame(0, DB::table('jobs')->count());

        Log::shouldHaveReceived('error')->once()->withArgs(
            static function (string $event, array $context): bool {
                $serializedContext = json_encode($context, JSON_THROW_ON_ERROR);

                return $event === 'unhandled_exception'
                    && ($context['exception_class'] ?? null) === RuntimeException::class
                    && ! str_contains($serializedContext, 'synthetic.person@example.test')
                    && ! str_contains($serializedContext, 'Synthetic Person')
                    && ! str_contains($serializedContext, 'EP02-NIM-482915')
                    && ! str_contains($serializedContext, '+62 812-3456-7890')
                    && ! str_contains($serializedContext, 'synthetic-password')
                    && ! str_contains($serializedContext, 'synthetic-token');
            },
        );
    }

    public function test_failed_recruitment_confirmation_job_can_be_retried_without_exposing_its_tracking_token(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'sqlite',
            'queue.connections.database.table' => 'jobs',
            'queue.connections.database.queue' => 'default',
            'queue.failed.driver' => 'database-uuids',
            'queue.failed.database' => 'sqlite',
            'queue.failed.table' => 'failed_jobs',
        ]);
        self::assertInstanceOf(RedactedDatabaseUuidFailedJobProvider::class, app('queue.failer'));

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $period = RecruitmentPeriod::factory()->create();
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $division->id,
            'personal_email' => 'applicant@example.test',
        ]);
        $trackingToken = 'tracking-token-canary-ep02-9f31d204';
        $mailFake = Mail::fake();
        $sendAttempts = 0;

        Mail::shouldReceive('to')
            ->twice()
            ->andReturnUsing(static function (string $recipient) use (&$sendAttempts, $mailFake) {
                $sendAttempts++;

                if ($sendAttempts === 1) {
                    throw new RuntimeException('Synthetic mail transport failure');
                }

                return $mailFake->to($recipient);
            });

        $job = new SendRecruitmentApplicationConfirmationJob($application->id, $trackingToken);
        $job->tries = 1;
        Queue::connection('database')->push($job);

        $queuedPayload = (string) DB::table('jobs')->value('payload');
        self::assertStringNotContainsString($trackingToken, $queuedPayload);

        $this->artisan('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
            '--sleep' => 0,
        ])->assertSuccessful();

        $failedJob = DB::table('failed_jobs')->first();
        self::assertNotNull($failedJob);
        self::assertStringNotContainsString($trackingToken, $failedJob->payload);
        self::assertSame(0, DB::table('jobs')->count());

        $this->artisan('queue:retry', ['id' => $failedJob->uuid])->assertSuccessful();
        self::assertSame(1, DB::table('jobs')->count());

        $this->artisan('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
            '--sleep' => 0,
        ])->assertSuccessful();

        $mailFake->assertSent(
            RecruitmentApplicationConfirmationMail::class,
            static fn (RecruitmentApplicationConfirmationMail $mail): bool => str_contains(
                $mail->bodyText,
                $trackingToken,
            ),
        );
        self::assertSame(0, DB::table('failed_jobs')->count());
        self::assertSame(0, DB::table('jobs')->count());
    }

    public function test_sensitive_job_and_chain_payloads_are_encrypted_in_the_database_queue(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'sqlite',
            'queue.connections.database.table' => 'jobs',
            'queue.connections.database.queue' => 'default',
        ]);

        $revisionNotes = 'synthetic-revision-note-canary-ep02-72c912';
        $whatsappGroupUrl = 'https://chat.whatsapp.com/synthetic-group-canary-ep02';
        $declineReason = 'synthetic-decline-reason-canary-ep02-54c813';

        Queue::connection('database')->push(new SendRecruitmentNotificationJob(
            applicationId: 'synthetic-application-id',
            templateKey: 'revision_required',
            revisionNotes: $revisionNotes,
            whatsappGroupUrl: $whatsappGroupUrl,
        ));
        Queue::connection('database')->push(new SendTeamInvitationLeaderNoticeJob(
            memberFormAnswerId: 'synthetic-form-answer-id',
            decision: 'rejected',
            declineReason: $declineReason,
        ));
        Bus::chain([
            new SendInviteeInvitationDeclinedNoticeJob('synthetic-form-answer-id'),
            new SendTeamInvitationLeaderNoticeJob(
                'synthetic-form-answer-id',
                'rejected',
                $declineReason,
            ),
            new DeleteDeclinedInvitationMemberSubmissionJob('synthetic-form-answer-id'),
        ])->dispatch();

        $queuedPayloads = DB::table('jobs')->orderBy('id')->pluck('payload')->all();
        self::assertCount(3, $queuedPayloads);

        $serializedPayloads = implode("\n", $queuedPayloads);
        self::assertStringNotContainsString($revisionNotes, $serializedPayloads);
        self::assertStringNotContainsString($whatsappGroupUrl, $serializedPayloads);
        self::assertStringNotContainsString($declineReason, $serializedPayloads);
    }

    public function test_failed_job_retention_schedule_uses_config_and_prunes_old_rows(): void
    {
        $retentionHours = (int) config('queue.failed.retention_hours');
        self::assertGreaterThan(0, $retentionHours);

        $this->artisan('schedule:list')
            ->expectsOutputToContain('queue:prune-failed --hours='.$retentionHours)
            ->assertSuccessful();

        self::assertInstanceOf(RedactedDatabaseUuidFailedJobProvider::class, app('queue.failer'));

        $failedAt = now();
        $expiredJobCount = 1001;
        $expiredFailedJobs = [];

        // Cross Laravel's 1,000-row prune batch to verify the inherited repeat loop.
        for ($index = 0; $index < $expiredJobCount; $index++) {
            $uuid = (string) Str::uuid();
            $expiredFailedJobs[] = [
                'uuid' => $uuid,
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode(['uuid' => $uuid], JSON_THROW_ON_ERROR),
                'exception' => 'RuntimeException: old failure',
                'failed_at' => $failedAt->copy()->subHours($retentionHours + 24),
            ];
        }

        $oldUuid = $expiredFailedJobs[0]['uuid'];
        $lastOldUuid = $expiredFailedJobs[$expiredJobCount - 1]['uuid'];
        $recentUuid = (string) Str::uuid();
        $failedRows = array_merge($expiredFailedJobs, [[
            'uuid' => $recentUuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['uuid' => $recentUuid], JSON_THROW_ON_ERROR),
            'exception' => 'RuntimeException: recent failure',
            'failed_at' => $failedAt->copy()->subHours(max($retentionHours - 24, 0)),
        ]]);

        foreach (array_chunk($failedRows, 100) as $failedJobChunk) {
            DB::table('failed_jobs')->insert($failedJobChunk);
        }

        $this->artisan('queue:prune-failed', ['--hours' => $retentionHours])->assertSuccessful();

        self::assertSame(1, DB::table('failed_jobs')->count());
        self::assertDatabaseMissing('failed_jobs', ['uuid' => $oldUuid]);
        self::assertDatabaseMissing('failed_jobs', ['uuid' => $lastOldUuid]);
        self::assertDatabaseHas('failed_jobs', ['uuid' => $recentUuid]);
    }

}

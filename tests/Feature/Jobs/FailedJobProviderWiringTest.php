<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendRegistrationOtpJob;
use App\Models\PendingRegistration;
use App\Support\RedactedDatabaseUuidFailedJobProvider;
use App\Support\RedactedFailedJobProvider;
use App\Support\RedactedPrunableFailedJobProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\Failed\FileFailedJobProvider;
use Illuminate\Queue\Failed\NullFailedJobProvider;
use Illuminate\Queue\Failed\PrunableFailedJobProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ProviderShapeFailingJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        throw new RuntimeException(
            'Could not notify synthetic.person@example.test; applicant=Synthetic Person; nim=EP02-NIM-482915; phone=+62 812-3456-7890; password=synthetic-password token=synthetic-token',
        );
    }
}

/**
 * Wiring coverage for AUDIT-P2-014 and AUDIT-P2-016: every failed-job provider shape must store a
 * redacted exception, keep the framework commands working, and never break the failure path.
 */
class FailedJobProviderWiringTest extends TestCase
{
    use RefreshDatabase;

    private const QUEUE_DATABASE_CONFIG = [
        'queue.default' => 'database',
        'queue.connections.database.connection' => 'sqlite',
        'queue.connections.database.table' => 'jobs',
        'queue.connections.database.queue' => 'default',
    ];

    public function test_each_laravel_failed_job_provider_shape_resolves_to_a_redacting_decorator(): void
    {
        $this->configureFailedJobs('database-uuids', ['database' => 'sqlite', 'table' => 'failed_jobs']);
        $uuidProvider = $this->resolvedFailer();
        self::assertInstanceOf(RedactedDatabaseUuidFailedJobProvider::class, $uuidProvider);
        self::assertInstanceOf(PrunableFailedJobProvider::class, $uuidProvider);
        self::assertSame($uuidProvider, $this->app->make(FailedJobProviderInterface::class));

        $this->configureFailedJobs('database', ['database' => 'sqlite', 'table' => 'failed_jobs']);
        $legacyDatabaseProvider = $this->resolvedFailer();
        self::assertInstanceOf(RedactedPrunableFailedJobProvider::class, $legacyDatabaseProvider);
        self::assertInstanceOf(DatabaseFailedJobProvider::class, $this->decoratedProvider($legacyDatabaseProvider));

        $this->configureFailedJobs('file', ['path' => $this->failedJobFilePath()]);
        $fileProvider = $this->resolvedFailer();
        self::assertInstanceOf(RedactedPrunableFailedJobProvider::class, $fileProvider);
        self::assertInstanceOf(FileFailedJobProvider::class, $this->decoratedProvider($fileProvider));

        $this->configureFailedJobs('null');
        $nullProvider = $this->resolvedFailer();
        self::assertInstanceOf(RedactedFailedJobProvider::class, $nullProvider);
        self::assertNotInstanceOf(RedactedPrunableFailedJobProvider::class, $nullProvider);
        self::assertInstanceOf(NullFailedJobProvider::class, $this->decoratedProvider($nullProvider));
    }

    public function test_generic_provider_failure_stores_only_the_class_and_redacted_message(): void
    {
        $path = $this->failedJobFilePath();
        config(array_merge(self::QUEUE_DATABASE_CONFIG, [
            'queue.failed' => [
                'driver' => 'file',
                'path' => $path,
                'database' => 'sqlite',
                'table' => 'failed_jobs',
                'retention_hours' => 168,
            ],
        ]));
        $this->app->forgetInstance('queue.failer');

        $failer = $this->resolvedFailer();
        self::assertInstanceOf(RedactedPrunableFailedJobProvider::class, $failer);

        Queue::connection('database')->push(new ProviderShapeFailingJob());

        $this->artisan('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
            '--sleep' => 0,
        ])->assertSuccessful();

        $stored = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $stored);

        $exception = $stored[0]['exception'];
        self::assertSame(RuntimeException::class.': [message redacted]', $exception);
        self::assertStringNotContainsString('Stack trace', $exception);
        self::assertStringNotContainsString('Object(', $exception);
        self::assertStringNotContainsString('synthetic.person@example.test', $exception);
        self::assertStringNotContainsString('Synthetic Person', $exception);
        self::assertStringNotContainsString('EP02-NIM-482915', $exception);
        self::assertStringNotContainsString('+62 812-3456-7890', $exception);
        self::assertStringNotContainsString('synthetic-password', $exception);
        self::assertStringNotContainsString('synthetic-token', $exception);

        $failedId = $stored[0]['id'];
        self::assertSame([$failedId], $failer->ids());
        self::assertSame($failedId, $failer->find($failedId)?->id);
        self::assertCount(1, $failer->all());
        self::assertTrue($failer->forget($failedId));
        self::assertSame([], $failer->all());

        @unlink($path);
    }

    public function test_generic_database_provider_keeps_pruning_through_the_decorator(): void
    {
        $this->configureFailedJobs('database', ['database' => 'sqlite', 'table' => 'failed_jobs']);
        $failer = $this->resolvedFailer();
        self::assertInstanceOf(PrunableFailedJobProvider::class, $failer);
        self::assertInstanceOf(DatabaseFailedJobProvider::class, $this->decoratedProvider($failer));

        // The legacy database driver does not write `uuid`, which this schema requires, so rows are
        // seeded directly: only pruning through the decorator is under test here.
        $expiredUuid = (string) Str::uuid();
        $recentUuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            [
                'uuid' => $expiredUuid,
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode(['uuid' => $expiredUuid], JSON_THROW_ON_ERROR),
                'exception' => 'RuntimeException: old failure',
                'failed_at' => now()->subDays(30),
            ],
            [
                'uuid' => $recentUuid,
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode(['uuid' => $recentUuid], JSON_THROW_ON_ERROR),
                'exception' => 'RuntimeException: recent failure',
                'failed_at' => now(),
            ],
        ]);

        $this->artisan('queue:prune-failed', ['--hours' => 168])->assertSuccessful();

        self::assertDatabaseMissing('failed_jobs', ['uuid' => $expiredUuid]);
        self::assertDatabaseHas('failed_jobs', ['uuid' => $recentUuid]);
    }

    public function test_unknown_failed_job_provider_is_kept_instead_of_breaking_the_failure_path(): void
    {
        $foreignProvider = new class () {
            public function log(): void
            {
            }
        };

        Log::spy();
        $this->app->bind('queue.failer', static fn (): object => $foreignProvider);
        $this->app->forgetInstance('queue.failer');

        self::assertSame($foreignProvider, $this->app->make('queue.failer'));

        Log::shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $event, array $context = []): bool => $event === 'queue.failed_provider_not_redacted'
                && ($context['provider'] ?? null) === $foreignProvider::class,
        );
    }

    public function test_permanent_otp_transport_failure_stores_no_previous_chain_or_pii(): void
    {
        config(array_merge(self::QUEUE_DATABASE_CONFIG, [
            'queue.failed' => [
                'driver' => 'database-uuids',
                'database' => 'sqlite',
                'table' => 'failed_jobs',
                'retention_hours' => 168,
            ],
        ]));
        $this->app->forgetInstance('queue.failer');

        $pending = PendingRegistration::create([
            'name' => 'Synthetic Person',
            'email' => 'otp.person@example.test',
            'password' => 'rahasia123',
        ]);
        $pending->forceFill([
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
            'otp_last_sent_at' => now(),
        ])->save();

        $transportMessage = 'SMTP rejected otp.person@example.test; applicant=Synthetic Person; nim=EP02-NIM-482915; phone=+62 812-3456-7890 token=synthetic-transport-token';

        Log::spy();
        Mail::shouldReceive('to->send')->once()->andThrow(new RuntimeException($transportMessage));

        // The job defines its own retry policy, which overrides `queue:work --tries`, so the
        // retry budget is lowered here to reach the permanent-failure path in one attempt.
        $otpJob = new SendRegistrationOtpJob(
            (string) $pending->id,
            $pending->otp_last_sent_at->toIso8601String(),
        );
        $otpJob->tries = 1;
        Queue::connection('database')->push($otpJob);

        $this->artisan('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
            '--sleep' => 0,
        ])->assertSuccessful();

        self::assertSame(0, DB::table('jobs')->count());
        $failedJob = DB::table('failed_jobs')->first();
        self::assertNotNull($failedJob);
        self::assertSame('App\Exceptions\RedactedMailDeliveryException: [message redacted]', $failedJob->exception);
        self::assertStringNotContainsString('Next ', $failedJob->exception);
        self::assertStringNotContainsString($transportMessage, $failedJob->exception);
        self::assertStringNotContainsString('otp.person@example.test', $failedJob->exception);
        self::assertStringNotContainsString('Synthetic Person', $failedJob->exception);
        self::assertStringNotContainsString('synthetic-transport-token', $failedJob->exception);
        self::assertStringNotContainsString('RuntimeException', $failedJob->exception);
    }

    public function test_failed_job_retention_default_is_positive_and_scheduled_daily(): void
    {
        $retentionHours = (int) config('queue.failed.retention_hours');

        self::assertGreaterThan(0, $retentionHours);

        $this->artisan('schedule:list')
            ->expectsOutputToContain('queue:prune-failed --hours='.$retentionHours)
            ->assertSuccessful();
    }

    public function test_legacy_database_driver_warns_instead_of_breaking_the_failure_path(): void
    {
        Log::spy();
        $this->configureFailedJobs('database', ['database' => 'sqlite', 'table' => 'failed_jobs']);

        $failer = $this->resolvedFailer();

        self::assertInstanceOf(RedactedPrunableFailedJobProvider::class, $failer);
        self::assertInstanceOf(DatabaseFailedJobProvider::class, $this->decoratedProvider($failer));

        Log::shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $event, array $context = []): bool => $event === 'queue.failed_provider_legacy_database'
                && ($context['provider'] ?? null) === DatabaseFailedJobProvider::class,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function configureFailedJobs(string $driver, array $extra = []): void
    {
        config(['queue.failed' => array_merge([
            'driver' => $driver,
            'database' => 'sqlite',
            'table' => 'failed_jobs',
            'retention_hours' => 168,
        ], $extra)]);
        $this->app->forgetInstance('queue.failer');
    }

    private function resolvedFailer(): FailedJobProviderInterface
    {
        $failer = $this->app->make('queue.failer');

        self::assertInstanceOf(FailedJobProviderInterface::class, $failer);

        return $failer;
    }

    private function decoratedProvider(FailedJobProviderInterface $failer): object
    {
        $property = new \ReflectionProperty(RedactedFailedJobProvider::class, 'provider');
        $property->setAccessible(true);
        $decorated = $property->getValue($failer);

        self::assertIsObject($decorated);

        return $decorated;
    }

    private function failedJobFilePath(): string
    {
        return storage_path('framework/testing/failed-jobs-wiring-'.Str::uuid().'.json');
    }
}

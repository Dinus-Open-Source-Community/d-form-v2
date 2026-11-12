<?php

namespace Tests\Feature\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentQrDispatchOutbox;
use App\Models\User;
use App\Services\Recruitment\RecruitmentQrDispatchRecovery;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Two-connection proof of the dispatch invariant. SQLite cannot model row locks, so this class
 * runs only against a disposable MySQL database through the committed `phpunit.race.xml`
 * (`RUN_MYSQL_RACE_TESTS=1`); it skips itself everywhere else. Preparation command: see
 * `docs/adr/0001-recruitment-qr-outbox.md`, section "How to run the MySQL concurrency and plan
 * suite".
 *
 * Invariants asserted here:
 * 1. A pending intent is claimed (and therefore dispatched) at most once, even when two
 *    connections claim it concurrently; `dispatch_attempts` increments at most once per claim.
 * 2. A concurrent claim waits on the row lock instead of succeeding against a snapshot.
 * 3. Two recovery polls serialize: the blocked poll sees the committed `queued` state and
 *    dispatches nothing.
 *
 * No RefreshDatabase: the wrapping transaction would hide the seeded rows from the second
 * connection. The class therefore deletes its own rows instead of rolling back.
 */
class RecruitmentQrOutboxClaimConcurrencyTest extends TestCase
{
    private const RACE_CONNECTION = 'mysql_race';

    /** @var list<string> */
    private array $createdApplicationIds = [];

    private ?string $createdPeriodId = null;

    private ?string $createdUserId = null;

    /** @var list<string> */
    private array $createdEmailLogIds = [];

    protected function tearDown(): void
    {
        if ($this->createdEmailLogIds !== []) {
            DB::connection('mysql')->table('recruitment_qr_dispatch_outboxes')
                ->whereIn('email_log_id', $this->createdEmailLogIds)
                ->delete();
            DB::connection('mysql')->table('email_logs')
                ->whereIn('id', $this->createdEmailLogIds)
                ->delete();
        }

        if ($this->createdApplicationIds !== []) {
            DB::connection('mysql')->table('recruitment_applications')
                ->whereIn('id', $this->createdApplicationIds)
                ->delete();
        }

        if ($this->createdPeriodId !== null) {
            DB::connection('mysql')->table('recruitment_periods')
                ->where('id', $this->createdPeriodId)
                ->delete();
        }

        if ($this->createdUserId !== null) {
            DB::connection('mysql')->table('users')->where('id', $this->createdUserId)->delete();
        }

        parent::tearDown();
    }

    public function test_concurrent_claim_waits_for_the_row_lock_and_then_becomes_a_no_op(): void
    {
        if (! $this->isDisposableMysqlTarget()) {
            $this->markTestSkipped(
                'MySQL race test requires RUN_MYSQL_RACE_TESTS=1 and a disposable database name.',
            );
        }

        Queue::fake();

        $application = $this->seedPendingIntent();
        $outbox = RecruitmentQrDispatchOutbox::query()
            ->whereKey($this->createdEmailLogIds[0])
            ->firstOrFail();
        $recovery = app(RecruitmentQrDispatchRecovery::class);

        $this->registerRaceConnection();
        $race = DB::connection(self::RACE_CONNECTION);
        $race->statement('SET SESSION innodb_lock_wait_timeout = 1');

        // Connection A holds the row exactly like the recovery poll does after SELECT ... FOR UPDATE.
        DB::connection('mysql')->beginTransaction();
        RecruitmentQrDispatchOutbox::query()
            ->whereKey($outbox->email_log_id)
            ->lockForUpdate()
            ->first();

        // Connection B claims the same intent through the production service while A holds it.
        DB::setDefaultConnection(self::RACE_CONNECTION);

        try {
            self::assertTrue(
                $this->claimExpectingLockWait($recovery, $outbox, (string) $application->id),
                'Klaim konkuren harus menunggu row lock, bukan langsung berhasil.',
            );
        } finally {
            DB::setDefaultConnection('mysql');
            DB::connection('mysql')->rollBack();
        }

        Queue::assertNothingPushed();
        Queue::assertNotPushed(SendRecruitmentNotificationJob::class);

        // A finishes its claim and commits `queued`; B retries the same claim afterwards.
        DB::connection('mysql')->beginTransaction();
        RecruitmentQrDispatchOutbox::query()
            ->whereKey($outbox->email_log_id)
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_PENDING)
            ->update([
                'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
                'dispatch_attempts' => DB::raw('dispatch_attempts + 1'),
            ]);
        DB::connection('mysql')->commit();

        DB::setDefaultConnection(self::RACE_CONNECTION);

        try {
            $recovery->enqueueOutbox(
                outbox: $outbox,
                applicationId: (string) $application->id,
                slot: now(),
            );
        } finally {
            DB::setDefaultConnection('mysql');
        }

        Queue::assertNothingPushed();
        Queue::assertNotPushed(SendRecruitmentNotificationJob::class);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $outbox->email_log_id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
            'dispatch_attempts' => 1,
        ]);
    }

    /**
     * Dua poll recovery bersamaan tidak boleh meng-enqueue intent yang sama: poll kedua
     * menunggu row lock, lalu hanya melihat state committed milik poll pertama.
     */
    public function test_concurrent_recovery_polls_serialize_on_the_pending_row_lock(): void
    {
        if (! $this->isDisposableMysqlTarget()) {
            $this->markTestSkipped(
                'MySQL race test requires RUN_MYSQL_RACE_TESTS=1 and a disposable database name.',
            );
        }

        Queue::fake();

        $this->seedPendingIntent();
        $recovery = app(RecruitmentQrDispatchRecovery::class);

        $this->registerRaceConnection();
        DB::connection(self::RACE_CONNECTION)
            ->statement('SET SESSION innodb_lock_wait_timeout = 1');

        DB::connection('mysql')->beginTransaction();
        RecruitmentQrDispatchOutbox::query()
            ->whereKey($this->createdEmailLogIds[0])
            ->lockForUpdate()
            ->first();

        DB::setDefaultConnection(self::RACE_CONNECTION);
        $blocked = false;

        try {
            try {
                $recovery->dispatchPending();
            } catch (QueryException $exception) {
                self::assertStringContainsString('Lock wait timeout', $exception->getMessage());
                $blocked = true;
            }
        } finally {
            DB::setDefaultConnection('mysql');
            DB::connection('mysql')->rollBack();
        }

        self::assertTrue($blocked, 'Poll kedua harus menunggu row lock milik poll pertama.');
        Queue::assertNotPushed(SendRecruitmentNotificationJob::class);

        RecruitmentQrDispatchOutbox::query()
            ->whereKey($this->createdEmailLogIds[0])
            ->where('status', RecruitmentQrDispatchOutbox::STATUS_PENDING)
            ->update([
                'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
                'dispatch_attempts' => DB::raw('dispatch_attempts + 1'),
            ]);

        DB::setDefaultConnection(self::RACE_CONNECTION);

        try {
            self::assertSame(
                0,
                $recovery->dispatchPending(),
                'Poll setelah state committed tidak boleh meng-enqueue ulang baris queued.',
            );
        } finally {
            DB::setDefaultConnection('mysql');
        }

        Queue::assertNotPushed(SendRecruitmentNotificationJob::class);
        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $this->createdEmailLogIds[0],
            'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
            'dispatch_attempts' => 1,
        ]);
    }

    private function claimExpectingLockWait(
        RecruitmentQrDispatchRecovery $recovery,
        RecruitmentQrDispatchOutbox $outbox,
        string $applicationId,
    ): bool {
        try {
            $recovery->enqueueOutbox(
                outbox: $outbox,
                applicationId: $applicationId,
                slot: now(),
            );
        } catch (QueryException $exception) {
            // The claim may only fail because the row lock is held by the other transaction.
            self::assertStringContainsString('Lock wait timeout', $exception->getMessage());

            return true;
        }

        return false;
    }

    private function seedPendingIntent(): RecruitmentApplication
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $staff = User::factory()->create();
        $this->createdUserId = $staff->id;
        $period = RecruitmentPeriod::factory()->create(['created_by' => $staff->id]);
        $this->createdPeriodId = $period->id;
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);
        $this->createdApplicationIds[] = $application->id;

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
        $this->createdEmailLogIds[] = $emailLog->id;

        RecruitmentQrDispatchOutbox::query()->create([
            'email_log_id' => $emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_PENDING,
            'available_at' => now(),
            'delay_seconds' => 0,
            'dispatch_attempts' => 0,
        ]);

        return $application;
    }

    private function registerRaceConnection(): void
    {
        config([
            'database.connections.'.self::RACE_CONNECTION => array_merge(
                (array) config('database.connections.mysql'),
                ['database' => DB::connection('mysql')->getDatabaseName()],
            ),
        ]);
        DB::purge(self::RACE_CONNECTION);
    }

    /** The race must never run against a shared database: only an explicitly disposable name. */
    private function isDisposableMysqlTarget(): bool
    {
        $optIn = $_SERVER['RUN_MYSQL_RACE_TESTS'] ?? getenv('RUN_MYSQL_RACE_TESTS');

        if ($optIn !== '1' || DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        $database = DB::connection('mysql')->getDatabaseName();

        if (preg_match('/\A[a-z0-9]+(?:_[a-z0-9]+)*_(?:test|testing|e2e)\z/i', $database) !== 1) {
            return false;
        }

        return Schema::connection('mysql')->hasTable('recruitment_qr_dispatch_outboxes')
            && Schema::connection('mysql')->hasTable('email_logs')
            && Schema::connection('mysql')->hasTable('recruitment_applications');
    }
}

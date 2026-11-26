<?php

namespace Tests\Feature\Forms;

use App\Enums\DuRedeemStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Models\DuCourse;
use App\Models\DuRedeemIssuance;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Services\Du\DuRedeemIssuanceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Support\DuRedeemFixture;
use Tests\TestCase;

/**
 * Two-connection proof of the DU issuance claim invariant. SQLite cannot model row locks, so this
 * class runs only against a disposable MySQL database through the committed `phpunit.race.xml`
 * (`RUN_MYSQL_RACE_TESTS=1`); it skips itself everywhere else.
 *
 * Invariants asserted here:
 * 1. Row-lock serialization: while one connection holds `lockForUpdate()` on the answer's
 *    `du_redeem_issuances` row, a concurrent `issueAccepted` waits and fails with a lock-wait
 *    timeout, so the DU redeem API is never called twice.
 * 2. Stale-read protection: after a committed `unknown` (claimed) issuance, a later
 *    `issueAccepted` reports "already claimed" and sends no DU request. Email outbox
 *    rows are created outside this service, so their invariant lives in
 *    `DuRedeemIssuedOutboxTest`.
 * 3. Single claim row per answer: sequential claim attempts never produce more than one
 *    `du_redeem_issuances` row for the same `form_answer_id`.
 *
 * No RefreshDatabase: the wrapping transaction would hide seeded rows from the second
 * connection. The class therefore deletes its own rows instead of rolling back.
 */
class DuRedeemIssuanceConcurrencyTest extends TestCase
{
    private const RACE_CONNECTION = 'mysql_race';

    private ?string $createdUserId = null;

    private ?string $createdEventId = null;

    private ?string $createdFormId = null;

    /** @var list<string> */
    private array $createdAnswerIds = [];

    private ?string $createdCourseUid = null;

    protected function tearDown(): void
    {
        if ($this->createdCourseUid !== null) {
            DB::connection('mysql')->table('du_courses')->where('uid', $this->createdCourseUid)->delete();
        }

        if ($this->createdAnswerIds !== []) {
            DB::connection('mysql')->table('form_review_email_outboxes')
                ->whereIn('form_answer_id', $this->createdAnswerIds)
                ->delete();
            DB::connection('mysql')->table('du_redeem_issuances')
                ->whereIn('form_answer_id', $this->createdAnswerIds)
                ->delete();
            DB::connection('mysql')->table('form_answers')
                ->whereIn('id', $this->createdAnswerIds)
                ->delete();
        }

        if ($this->createdFormId !== null) {
            DB::connection('mysql')->table('forms')->where('id', $this->createdFormId)->delete();
        }

        if ($this->createdEventId !== null) {
            DB::connection('mysql')->table('events')->where('id', $this->createdEventId)->delete();
        }

        if ($this->createdUserId !== null) {
            DB::connection('mysql')->table('users')->where('id', $this->createdUserId)->delete();
        }

        parent::tearDown();
    }

    public function test_concurrent_issuance_waits_for_the_row_lock_instead_of_calling_du_twice(): void
    {
        $this->skipUnlessDisposableMysqlRaceTarget();

        Queue::fake();
        $this->seedCachedCourseAndFakeDuRedeem();
        $answer = $this->seedAcceptedDuAnswer();
        $service = app(DuRedeemIssuanceService::class);

        DuRedeemIssuance::query()->create([
            'form_answer_id' => $answer->id,
            'course_uid' => 'course-test',
            'status' => DuRedeemStatus::Pending,
            'attempt_count' => 0,
        ]);

        $this->registerRaceConnection();
        DB::connection(self::RACE_CONNECTION)->statement('SET SESSION innodb_lock_wait_timeout = 1');

        // Connection A holds the claim row exactly like the production claim transaction does.
        DB::connection('mysql')->beginTransaction();
        DuRedeemIssuance::query()
            ->where('form_answer_id', $answer->id)
            ->lockForUpdate()
            ->first();

        // Connection B issues the same answer through the production service while A holds it.
        DB::setDefaultConnection(self::RACE_CONNECTION);
        $blocked = false;

        try {
            try {
                $service->issueAccepted($answer);
            } catch (QueryException $exception) {
                self::assertStringContainsString('Lock wait timeout', $exception->getMessage());
                $blocked = true;
            }
        } finally {
            DB::setDefaultConnection('mysql');
            DB::connection('mysql')->rollBack();
        }

        self::assertTrue($blocked, 'Klaim konkuren harus menunggu row lock, bukan langsung berhasil.');
        $this->assertSame(0, $this->redeemApiCallCount(), 'DU redeem API tidak boleh dipanggil saat row lock ditahan.');
        $this->assertSame(1, DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->count());
        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => DuRedeemStatus::Pending->value,
        ]);
    }

    public function test_committed_unknown_claim_blocks_a_second_du_call(): void
    {
        $this->skipUnlessDisposableMysqlRaceTarget();

        Queue::fake();
        $this->seedCachedCourseAndFakeDuRedeem();
        $answer = $this->seedAcceptedDuAnswer();
        $service = app(DuRedeemIssuanceService::class);

        // Connection A claims the answer and commits it as `unknown` (upstream outcome not stored).
        DB::connection('mysql')->transaction(function () use ($answer): void {
            $issuance = DuRedeemIssuance::query()
                ->where('form_answer_id', $answer->id)
                ->lockForUpdate()
                ->first();

            if ($issuance === null) {
                $issuance = DuRedeemIssuance::query()->create([
                    'form_answer_id' => $answer->id,
                    'course_uid' => 'course-test',
                    'status' => DuRedeemStatus::Pending,
                    'attempt_count' => 0,
                ]);
            }

            $issuance->forceFill([
                'status' => DuRedeemStatus::Unknown,
                'attempted_at' => now(),
                'attempt_count' => $issuance->attempt_count + 1,
            ])->save();
        });

        $this->registerRaceConnection();
        DB::setDefaultConnection(self::RACE_CONNECTION);
        $result = null;

        try {
            $result = $service->issueAccepted($answer);
        } finally {
            DB::setDefaultConnection('mysql');
        }

        self::assertSame(DuRedeemStatus::Unknown, $result->status);
        self::assertSame('Issuance is already claimed.', $result->message);
        $this->assertSame(0, $this->redeemApiCallCount());
        $this->assertSame(1, DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->count());
        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => DuRedeemStatus::Unknown->value,
        ]);
    }

    public function test_sequential_claims_keep_a_single_issuance_row(): void
    {
        $this->skipUnlessDisposableMysqlRaceTarget();

        Queue::fake();
        $this->seedCachedCourseAndFakeDuRedeem();
        $answer = $this->seedAcceptedDuAnswer();
        $service = app(DuRedeemIssuanceService::class);

        $this->registerRaceConnection();
        DB::setDefaultConnection(self::RACE_CONNECTION);
        $first = null;
        $second = null;

        try {
            $first = $service->issueAccepted($answer);
            $second = $service->issueAccepted($answer);
        } finally {
            DB::setDefaultConnection('mysql');
        }

        self::assertSame(DuRedeemStatus::Issued, $first->status);
        self::assertSame(DuRedeemStatus::Issued, $second->status);
        self::assertSame('safe-test-code', $second->code);
        self::assertSame('Issuance is already claimed.', $second->message);
        $this->assertSame(1, DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->count());
        $this->assertSame(1, $this->redeemApiCallCount());
        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => DuRedeemStatus::Issued->value,
            'redeem_code' => 'safe-test-code',
        ]);
    }

    private function skipUnlessDisposableMysqlRaceTarget(): void
    {
        if (! $this->isDisposableMysqlTarget()) {
            $this->markTestSkipped(
                'MySQL race test requires RUN_MYSQL_RACE_TESTS=1 and a disposable database name.',
            );
        }
    }

    private function seedAcceptedDuAnswer(): FormAnswer
    {
        $user = User::factory()->create();
        $this->createdUserId = $user->id;

        $event = Event::factory()->create(['created_by' => $user->id]);
        $this->createdEventId = $event->id;

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single'],
        ]);
        $this->createdFormId = $form->id;

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'answers' => ['du_course_uid' => 'course-test'],
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);
        $this->createdAnswerIds[] = $answer->id;

        return $answer;
    }

    private function redeemApiCallCount(): int
    {
        $count = 0;

        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), '/api/external/redeem-codes')) {
                $count++;
            }
        }

        return $count;
    }

    private function seedCachedCourseAndFakeDuRedeem(): void
    {
        $course = DuCourse::query()->firstOrCreate(['uid' => 'course-test'], [
            'title' => 'Test',
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        if ($course->wasRecentlyCreated) {
            $this->createdCourseUid = $course->uid;
        }

        Http::fake(fn () => Http::response([
            'success' => true,
            'data' => DuRedeemFixture::success('safe-test-code'),
        ], 201));
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

        return Schema::connection('mysql')->hasTable('du_redeem_issuances')
            && Schema::connection('mysql')->hasTable('form_review_email_outboxes')
            && Schema::connection('mysql')->hasTable('form_answers')
            && Schema::connection('mysql')->hasTable('forms')
            && Schema::connection('mysql')->hasTable('events');
    }
}

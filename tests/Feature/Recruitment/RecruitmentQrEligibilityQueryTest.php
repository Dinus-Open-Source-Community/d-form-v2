<?php

namespace Tests\Feature\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\AttendanceMethod;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentQrBulkService;
use App\Services\Recruitment\RecruitmentQrEligibilityQuery;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * BE-P2-003: satu sumber kebenaran eligibility QR interview.
 *
 * Predikat notSent/sent dipakai controller dan bulk service; test ini mengunci
 * hasilnya terhadap perilaku bulk service yang sudah ada, keberadaan kedua
 * index komposit, dan rencana eksekusi MySQL yang sebenarnya.
 */
class RecruitmentQrEligibilityQueryTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private RecruitmentPeriod $period;

    private RecruitmentPeriod $otherPeriod;

    private RecruitmentDivision $division;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        config(['registration.email_send_delay_seconds' => 0]);

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        // Aksi tulis periode (sendQr/sendGroupLink) membutuhkan permission, bukan ownership.
        $this->staff->givePermissionTo('recruitment.periods.edit');

        $this->period = RecruitmentPeriod::factory()->create(['created_by' => $this->staff->id]);
        $this->otherPeriod = RecruitmentPeriod::factory()->create(['created_by' => $this->staff->id]);
        $this->division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $this->session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->division->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    public function test_qr_eligibility_indexes_exist_with_expected_column_order(): void
    {
        $emailLogIndexes = collect(Schema::getIndexes('email_logs'))->keyBy('name');
        $applicationIndexes = collect(Schema::getIndexes('recruitment_applications'))->keyBy('name');

        $this->assertSame(
            ['recruitment_application_id', 'notification_type', 'status'],
            $emailLogIndexes['email_logs_rec_app_type_status_idx']['columns'],
        );
        $this->assertSame(
            ['recruitment_period_id', 'stage', 'cancelled_at'],
            $applicationIndexes['rec_apps_period_stage_cancelled_idx']['columns'],
        );
    }

    public function test_be_p2_019_count_eligible_matches_bulk_service_dispatched(): void
    {
        Queue::fake();
        $matrix = $this->seedEligibilityMatrix();

        $expectedOrder = collect($matrix)
            ->only(['eligible_first', 'eligible_other_type', 'eligible_failed'])
            ->sortBy('submitted_at')
            ->pluck('id')
            ->values()
            ->all();

        $before = $this->eligibilityQuery()->countEligible($this->period);
        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        $this->assertSame(3, $before);
        $this->assertSame($before, $result['scheduled']);
        $this->assertSame(
            $expectedOrder,
            Queue::pushed(SendRecruitmentNotificationJob::class)->pluck('applicationId')->values()->all(),
            'Recipients must stay ordered by submitted_at.',
        );

        // Semua kandidat yang baru dijadwalkan kini in-flight hingga job selesai.
        $this->assertSame(0, $this->eligibilityQuery()->countEligible($this->period));
    }

    public function test_be_p2_019_count_in_flight_matches_bulk_service_skipped(): void
    {
        Queue::fake();
        $matrix = $this->seedEligibilityMatrix();

        $before = $this->eligibilityQuery()->countInFlight($this->period);
        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        $this->assertSame(3, $before);
        $this->assertSame($before, $result['skipped']);
        $this->assertEqualsCanonicalizing(
            [
                $matrix['eligible_queued']->id,
                $matrix['already_sent']->id,
                $matrix['sent_then_queued']->id,
                $matrix['eligible_first']->id,
                $matrix['eligible_other_type']->id,
                $matrix['eligible_failed']->id,
            ],
            $this->eligibilityQuery()->inFlight($this->period)->pluck('id')->all(),
            'Queued and sent QR emails are in-flight and excluded from another default send.',
        );
        $this->assertSame(2, $this->eligibilityQuery()->countAlreadySent($this->period));

        // Explicit resend keeps the sent-only applicant eligible but skips all queued QR rows.
        $resend = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period, true);
        $this->assertSame(['scheduled' => 1, 'skipped' => 0], $resend);
    }

    public function test_be_p2_019_counts_keeps_queued_separate_from_sent(): void
    {
        $this->seedEligibilityMatrix();

        $query = $this->eligibilityQuery();

        $this->assertSame(['eligible' => 3, 'sent' => 2], $query->counts($this->period));
        // eligible = base - in-flight; queued is in-flight but is not counted as sent.
        $this->assertSame(6, $query->base($this->period)->count());
        $this->assertSame(3, $query->countInFlight($this->period));
        $this->assertSame(2, $query->countAlreadySent($this->period));
        $this->assertSame(3, $query->countEligible($this->period));
    }

    public function test_be_p2_019_period_show_props_match_query_class(): void
    {
        $this->seedEligibilityMatrix();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'peserta']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('qr_eligible_count', 3)
                ->where('qr_sent_count', 2));
    }

    public function test_mysql_query_plan_requires_explicit_opt_in_testing_environment_and_disposable_database(): void
    {
        $this->assertFalse(self::isSafeMysqlQueryPlanTarget('', 'testing', 'd_form_test'));
        $this->assertFalse(self::isSafeMysqlQueryPlanTarget('1', 'local', 'd_form_test'));
        $this->assertFalse(self::isSafeMysqlQueryPlanTarget('1', 'testing', 'd_form_app'));
        $this->assertFalse(self::isSafeMysqlQueryPlanTarget('1', 'testing', 'd_form'));
        $this->assertTrue(self::isSafeMysqlQueryPlanTarget('1', 'testing', 'd_form_test'));
    }

    /**
     * Penjaga performa: SQL asli dari `counts()` harus memakai index komposit
     * (covering) di kedua tabel. Read-only untuk data aplikasi; menulis baris
     * `email_logs` sementara bertanda unik untuk memberi optimizer dataset yang
     * representatif, lalu menghapusnya di `finally`. Hanya berjalan dengan opt-in
     * eksplisit, `APP_ENV=testing`, dan database bernama disposable; kegagalan
     * koneksi dilaporkan tanpa detail exception.
     */
    public function test_be_p2_019_count_query_uses_both_composite_indexes_on_mysql(): void
    {
        $database = config('database.connections.mysql.database');

        if (! self::isSafeMysqlQueryPlanTarget(
            getenv('RUN_MYSQL_QUERY_PLAN_TESTS'),
            app()->environment(),
            $database,
        )) {
            $this->markTestSkipped(
                'MySQL query-plan test requires RUN_MYSQL_QUERY_PLAN_TESTS=1, APP_ENV=testing, and a disposable database name.',
            );
        }

        $originalDatabase = config('database.connections.mysql.database');
        $seededLogIds = [];

        try {
            config(['database.connections.mysql.database' => $database]);
            DB::purge('mysql');

            try {
                $connection = DB::connection('mysql');
                $connection->getPdo();
            } catch (\Throwable) {
                $this->markTestSkipped('MySQL query-plan connection is unavailable.');
            }

            foreach (['recruitment_applications', 'email_logs', 'recruitment_periods'] as $table) {
                if (! Schema::connection('mysql')->hasTable($table)) {
                    $this->markTestSkipped('Tabel '.$table.' tidak ada di MySQL.');
                }
            }

            $requiredIndexes = [
                'recruitment_applications' => 'rec_apps_period_stage_cancelled_idx',
                'email_logs' => 'email_logs_rec_app_type_status_idx',
            ];

            foreach ($requiredIndexes as $table => $index) {
                if (! Schema::connection('mysql')->hasIndex($table, $index)) {
                    $this->markTestSkipped('Index '.$index.' belum dibuat di MySQL.');
                }
            }

            // Periode terpadat pada tahap interview: plan paling representatif,
            // bukan baris pertama yang kebetulan kosong.
            $periodId = $connection->table('recruitment_applications')
                ->where('stage', 'interview')
                ->groupBy('recruitment_period_id')
                ->orderByRaw('count(*) desc')
                ->value('recruitment_period_id');

            if ($periodId === null) {
                $this->markTestSkipped('Tidak ada applicant tahap interview di MySQL.');
            }

            $applicationIds = $connection->table('recruitment_applications')
                ->where('recruitment_period_id', $periodId)
                ->where('stage', 'interview')
                ->whereNull('cancelled_at')
                ->limit(2000)
                ->pluck('id')
                ->all();

            if ($applicationIds === []) {
                $this->markTestSkipped('Periode terpadat tidak memiliki applicant interview aktif.');
            }

            // Seed sent logs sementara agar EXISTS benar-benar memilih composite
            // index; tanpa baris matching, optimizer bisa memilih plan nol-biaya.
            $now = now();

            foreach ($applicationIds as $applicationId) {
                $logId = (string) Str::uuid();
                $seededLogIds[] = $logId;
                $connection->table('email_logs')->insert([
                    'id' => $logId,
                    'form_answer_id' => null,
                    'recruitment_application_id' => $applicationId,
                    'event_id' => null,
                    'user_id' => null,
                    'recipient_email' => 'explain-guard@example.test',
                    'status' => 'sent',
                    'notification_type' => 'recruitment_interview_scheduled',
                    'error_message' => null,
                    'sent_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'delivery_key' => null,
                ]);
            }

            $connection->statement('ANALYZE TABLE email_logs');

            $captured = [];
            $connection->listen(static function (QueryExecuted $query) use (&$captured): void {
                $captured[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
            });

            // Jalankan query class pada koneksi mysql agar EXPLAIN memakai SQL asli
            // yang dikirim counts(), bukan SELECT tulis tangan yang bisa drift.
            $previousDefault = DB::getDefaultConnection();
            DB::setDefaultConnection('mysql');

            try {
                $period = RecruitmentPeriod::query()->findOrFail($periodId);
                $this->eligibilityQuery()->counts($period);
            } finally {
                DB::setDefaultConnection($previousDefault);
            }

            $baseQuery = collect($captured)->first(
                static fn (array $query): bool => str_starts_with($query['sql'], 'select count(')
                    && ! str_contains($query['sql'], 'email_logs'),
            );
            $existsQuery = collect($captured)->first(
                static fn (array $query): bool => str_starts_with($query['sql'], 'select count(')
                    && str_contains($query['sql'], 'exists')
                    && str_contains($query['sql'], 'email_logs')
                    && ! str_contains(strtolower($query['sql']), ' in '),
            );
            $inFlightQuery = collect($captured)->first(
                static fn (array $query): bool => str_starts_with($query['sql'], 'select count(')
                    && str_contains($query['sql'], 'exists')
                    && str_contains($query['sql'], 'email_logs')
                    && str_contains(strtolower($query['sql']), ' in '),
            );

            $this->assertIsArray($baseQuery, 'Query base count tidak tertangkap dari counts().');
            $this->assertIsArray($existsQuery, 'Query EXISTS sent tidak tertangkap dari counts().');
            $this->assertIsArray($inFlightQuery, 'Query EXISTS in-flight tidak tertangkap dari counts().');

            $explainStep = function (array $query, string $table) use ($connection): array {
                $sql = $connection->getQueryGrammar()
                    ->substituteBindingsIntoRawSql($query['sql'], $query['bindings']);

                return collect($connection->select('EXPLAIN '.$sql))
                    ->map(static fn (object $row): array => (array) $row)
                    ->first(static fn (array $row): bool => ($row['table'] ?? null) === $table) ?? [];
            };

            $baseApplicationStep = $explainStep($baseQuery, 'recruitment_applications');
            $existsEmailLogStep = $explainStep($existsQuery, 'email_logs');
            $inFlightEmailLogStep = $explainStep($inFlightQuery, 'email_logs');

            $this->assertSame(
                'rec_apps_period_stage_cancelled_idx',
                $baseApplicationStep['key'] ?? $baseApplicationStep['Key'] ?? null,
                'Base count harus memakai index covering applicant.',
            );
            $this->assertStringContainsStringIgnoringCase(
                'using index',
                (string) ($baseApplicationStep['Extra'] ?? $baseApplicationStep['extra'] ?? ''),
                'Base count applicant harus covering (Using index).',
            );

            // EXISTS punya dua plan yang sah dan terukur:
            //  a) probe korelasi per applicant → composite email_logs (covering);
            //  b) semi-join digerakkan dari email_logs → "Start temporary", index
            //     ref apa pun selain full scan.
            // Plan bergantung distribusi data; keduanya harus tetap terindeks.
            $existsKey = $existsEmailLogStep['key'] ?? $existsEmailLogStep['Key'] ?? null;
            $existsExtra = (string) ($existsEmailLogStep['Extra'] ?? $existsEmailLogStep['extra'] ?? '');
            $existsType = (string) ($existsEmailLogStep['type'] ?? $existsEmailLogStep['Type'] ?? '');

            if ($existsKey === 'email_logs_rec_app_type_status_idx') {
                $this->assertStringContainsStringIgnoringCase(
                    'using index',
                    $existsExtra,
                    'Saat composite email_logs dipakai, probe harus covering (Using index).',
                );
            } else {
                $this->assertStringContainsStringIgnoringCase(
                    'start temporary',
                    $existsExtra,
                    'Bila composite tidak dipilih, email_logs harus menjadi penggerak semi-join terindeks.',
                );
                $this->assertNotSame('ALL', strtoupper($existsType), 'email_logs tidak boleh full table scan.');
                $this->assertNotSame(
                    null,
                    $existsKey,
                    'Plan semi-join tetap harus memakai index email_logs, bukan scan tanpa index.',
                );
            }

            $inFlightKey = $inFlightEmailLogStep['key'] ?? $inFlightEmailLogStep['Key'] ?? null;
            $inFlightExtra = (string) ($inFlightEmailLogStep['Extra'] ?? $inFlightEmailLogStep['extra'] ?? '');
            $inFlightType = (string) ($inFlightEmailLogStep['type'] ?? $inFlightEmailLogStep['Type'] ?? '');

            if ($inFlightKey === 'email_logs_rec_app_type_status_idx') {
                $this->assertStringContainsStringIgnoringCase(
                    'using index',
                    $inFlightExtra,
                    'The in-flight status probe must be covering when the composite index is selected.',
                );
            } else {
                $this->assertStringContainsStringIgnoringCase(
                    'start temporary',
                    $inFlightExtra,
                    'The in-flight semi-join must be driven by an indexed email_logs lookup.',
                );
                $this->assertNotSame('ALL', strtoupper($inFlightType));
                $this->assertNotNull($inFlightKey);
            }
        } finally {
            if ($seededLogIds !== []) {
                DB::connection('mysql')->table('email_logs')->whereIn('id', $seededLogIds)->delete();
            }

            config(['database.connections.mysql.database' => $originalDatabase]);
            DB::purge('mysql');
        }
    }

    private static function isSafeMysqlQueryPlanTarget(string|false $optIn, string $appEnvironment, mixed $database): bool
    {
        if ($optIn !== '1' || $appEnvironment !== 'testing' || ! is_string($database)) {
            return false;
        }

        return preg_match('/\\A[a-z0-9]+(?:_[a-z0-9]+)*_(?:test|testing|e2e)\\z/i', $database) === 1;
    }

    /**
     * Satu matriks applicant yang mencakup setiap cabang predikat eligibility.
     *
     * @return array<string, RecruitmentApplication>
     */
    private function seedEligibilityMatrix(): array
    {
        $matrix = [
            'eligible_first' => $this->applicant(['submitted_at' => now()->subMinutes(4)]),
            'eligible_queued' => $this->applicant(['submitted_at' => now()->subMinutes(3)]),
            'eligible_other_type' => $this->applicant(['submitted_at' => now()->subMinutes(2)]),
            'eligible_failed' => $this->applicant(['submitted_at' => now()->subMinute()]),
            'already_sent' => $this->applicant(),
            'sent_then_queued' => $this->applicant(),
            'sent_and_checked_in' => $this->applicant(),
            'sent_and_cancelled' => $this->applicant(['cancelled_at' => now()]),
            'checked_in' => $this->applicant(),
            'cancelled' => $this->applicant(['cancelled_at' => now()]),
            'screening' => $this->applicant(['stage' => ApplicationStage::Screening]),
            'other_period' => $this->applicant(['recruitment_period_id' => $this->otherPeriod->id]),
        ];

        $this->emailLog($matrix['eligible_queued'], EmailLogStatus::Queued);
        $this->emailLog($matrix['eligible_failed'], EmailLogStatus::Failed);
        $this->emailLog($matrix['eligible_other_type'], EmailLogStatus::Sent, [
            'notification_type' => EmailNotificationType::RecruitmentGroupLink,
        ]);
        $this->emailLog($matrix['already_sent'], EmailLogStatus::Sent);
        // "Pernah terkirim" bersifat historis: Sent lama + Queued baru tetap
        // membuat applicant tidak masuk pool kirim (bukan log terbaru yang menang).
        $this->emailLog($matrix['sent_then_queued'], EmailLogStatus::Sent, ['created_at' => now()->subHour()]);
        $this->emailLog($matrix['sent_then_queued'], EmailLogStatus::Queued, ['created_at' => now()->subMinutes(30)]);
        $this->emailLog($matrix['sent_and_checked_in'], EmailLogStatus::Sent);
        $this->emailLog($matrix['sent_and_cancelled'], EmailLogStatus::Sent);

        foreach (['checked_in', 'sent_and_checked_in'] as $key) {
            RecruitmentAttendance::query()->create([
                'recruitment_application_id' => $matrix[$key]->id,
                'recruitment_interview_session_id' => $this->session->id,
                'method' => AttendanceMethod::RegistrationNumber,
                'checked_in_at' => now(),
                'checked_in_by' => $this->staff->id,
            ]);
        }

        return $matrix;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */

    /**
     * BE-P2-020: intent yang sudah `queued` harus dihitung in-flight di props halaman dan
     * `qr_status_summary`, sehingga tombol Kirim QR tidak menawarkan pengiriman duplikat.
     */
    public function test_be_p2_020_queued_intents_are_in_flight_in_page_props_and_status_summary(): void
    {
        Queue::fake();
        config(['recruitment.qr_send_rate_limit_per_minute' => 5]);

        foreach (range(1, 3) as $_sequence) {
            $this->applicant();
        }

        $this->actingAs($this->staff)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'peserta']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('qr_eligible_count', 3)
                ->where('qr_sent_count', 0)
                ->where('qr_status_summary.queued', 0));

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.send-qr', $this->period))
            ->assertRedirect();

        Queue::assertPushed(SendRecruitmentNotificationJob::class, 3);
        self::assertSame(['eligible' => 0, 'sent' => 0], $this->eligibilityQuery()->counts($this->period));

        $this->actingAs($this->staff)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'peserta']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('qr_eligible_count', 0)
                ->where('qr_sent_count', 0)
                ->where('qr_status_summary.queued', 3)
                ->where('qr_status_summary.sent', 0)
                ->where('qr_status_summary.failed', 0));

        // POST kedua selama intent masih queued tidak membuat email log/job baru.
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.send-qr', $this->period))
            ->assertRedirect()
            ->assertSessionHas('message', 'QR dijadwalkan untuk 0 applicant tahap interview.');

        self::assertSame(3, EmailLog::query()
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled)
            ->count());
        Queue::assertPushed(SendRecruitmentNotificationJob::class, 3);
    }

    private function applicant(array $overrides = []): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create(array_merge([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->division->id,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function emailLog(
        RecruitmentApplication $application,
        EmailLogStatus $status,
        array $attributes = [],
    ): EmailLog {
        $log = new EmailLog();
        $log->forceFill([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $application->personal_email,
            'status' => $status,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => $status === EmailLogStatus::Sent ? now() : null,
            ...$attributes,
        ])->save();

        return $log;
    }

    private function eligibilityQuery(): RecruitmentQrEligibilityQuery
    {
        return app(RecruitmentQrEligibilityQuery::class);
    }
}

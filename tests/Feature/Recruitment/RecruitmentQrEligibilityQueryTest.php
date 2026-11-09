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

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

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

    public function test_count_eligible_matches_bulk_service_dispatched(): void
    {
        Queue::fake();
        $matrix = $this->seedEligibilityMatrix();

        $expectedOrder = collect($matrix)
            ->only(['eligible_first', 'eligible_queued', 'eligible_other_type', 'eligible_failed'])
            ->sortBy('submitted_at')
            ->pluck('id')
            ->values()
            ->all();

        $before = $this->eligibilityQuery()->countEligible($this->period);
        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        $this->assertSame(4, $before);
        $this->assertSame($before, $result['dispatched']);
        $this->assertSame(
            $expectedOrder,
            Queue::pushed(SendRecruitmentNotificationJob::class)->pluck('applicationId')->values()->all(),
            'Recipients must stay ordered by submitted_at.',
        );

        // Queued rows dari pengiriman ini belum Sent, jadi kandidat tetap eligible.
        $this->assertSame(4, $this->eligibilityQuery()->countEligible($this->period));
    }

    public function test_count_already_sent_matches_bulk_service_skipped(): void
    {
        Queue::fake();
        $matrix = $this->seedEligibilityMatrix();

        $before = $this->eligibilityQuery()->countAlreadySent($this->period);
        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        $this->assertSame(2, $before);
        $this->assertSame($before, $result['skipped']);
        $this->assertEqualsCanonicalizing(
            [$matrix['already_sent']->id, $matrix['sent_then_queued']->id],
            $this->eligibilityQuery()->alreadySent($this->period)->pluck('id')->all(),
            'Pool kirim ulang = applicant yang pernah terkirim dan masih di base pool.',
        );

        // include_sent=true memakai base(): 4 kandidat + 2 pernah terkirim.
        $resend = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period, true);
        $this->assertSame(['dispatched' => 6, 'skipped' => 0], $resend);
    }

    public function test_counts_returns_literals_and_matches_component_queries(): void
    {
        $this->seedEligibilityMatrix();

        $query = $this->eligibilityQuery();

        $this->assertSame(['eligible' => 4, 'sent' => 2], $query->counts($this->period));
        // eligible = base - sent; base memuat 4 kandidat + 2 pernah terkirim.
        $this->assertSame(6, $query->base($this->period)->count());
        $this->assertSame(2, $query->countAlreadySent($this->period));
        $this->assertSame(4, $query->countEligible($this->period));
    }

    public function test_period_show_props_match_query_class(): void
    {
        $this->seedEligibilityMatrix();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'peserta']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('qr_eligible_count', 4)
                ->where('qr_sent_count', 2));
    }

    /**
     * Penjaga performa: SQL asli dari `countEligible()` harus memakai index
     * komposit (covering) di kedua tabel. Read-only pada koneksi `mysql`;
     * dilewati bila koneksi/index/data MySQL tidak tersedia.
     */
    public function test_count_query_uses_both_composite_indexes_on_mysql(): void
    {
        // phpunit.xml memaksa DB_DATABASE=:memory: untuk koneksi default (SQLite);
        // koneksi `mysql` tetap memakai nama database asli dari environment proses.
        $database = getenv('DB_DATABASE');

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            $this->markTestSkipped('Nama database MySQL tidak tersedia di environment.');
        }

        $originalDatabase = config('database.connections.mysql.database');

        try {
            config(['database.connections.mysql.database' => $database]);
            DB::purge('mysql');

            try {
                $connection = DB::connection('mysql');
                $connection->getPdo();
            } catch (\Throwable $exception) {
                $this->markTestSkipped('Koneksi MySQL tidak tersedia: '.$exception->getMessage());
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

            $captured = [];
            $connection->listen(static function (QueryExecuted $query) use (&$captured): void {
                $captured[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
            });

            // Jalankan query class pada koneksi mysql agar EXPLAIN memakai SQL asli
            // yang dikirim countEligible(), bukan SELECT tulis tangan yang bisa drift.
            $previousDefault = DB::getDefaultConnection();
            DB::setDefaultConnection('mysql');

            try {
                $period = RecruitmentPeriod::query()->findOrFail($periodId);
                $this->eligibilityQuery()->countEligible($period);
            } finally {
                DB::setDefaultConnection($previousDefault);
            }

            $countQuery = collect($captured)->first(
                static fn (array $query): bool => str_starts_with($query['sql'], 'select count(')
                    && str_contains($query['sql'], 'email_logs'),
            );

            $this->assertIsArray($countQuery, 'Query count tidak tertangkap dari countEligible().');

            $explainSql = $connection->getQueryGrammar()
                ->substituteBindingsIntoRawSql($countQuery['sql'], $countQuery['bindings']);

            $steps = collect($connection->select('EXPLAIN '.$explainSql))
                ->map(static fn (object $row): array => (array) $row);

            $applicationStep = $steps->first(static fn (array $row): bool => ($row['table'] ?? null) === 'recruitment_applications');
            $emailLogStep = $steps->first(static fn (array $row): bool => ($row['table'] ?? null) === 'email_logs');

            $this->assertIsArray($applicationStep, 'EXPLAIN tidak memuat baris recruitment_applications.');
            $this->assertIsArray($emailLogStep, 'EXPLAIN tidak memuat baris email_logs.');

            $this->assertSame(
                'rec_apps_period_stage_cancelled_idx',
                $applicationStep['key'] ?? $applicationStep['Key'] ?? null,
            );
            $this->assertStringContainsStringIgnoringCase(
                'using index',
                (string) ($applicationStep['Extra'] ?? $applicationStep['extra'] ?? ''),
                'Langkah recruitment_applications harus covering (Using index).',
            );
            $this->assertSame(
                'email_logs_rec_app_type_status_idx',
                $emailLogStep['key'] ?? $emailLogStep['Key'] ?? null,
            );
            $this->assertStringContainsStringIgnoringCase(
                'using index',
                (string) ($emailLogStep['Extra'] ?? $emailLogStep['extra'] ?? ''),
                'Langkah email_logs harus covering (Using index).',
            );
        } finally {
            config(['database.connections.mysql.database' => $originalDatabase]);
            DB::purge('mysql');
        }
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

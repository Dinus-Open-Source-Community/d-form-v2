<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\AttendanceMethod;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentFinalDecision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi keyset pagination export CSV interview: urutan, filter lintas halaman,
 * eager loading, dan jumlah query yang tidak tumbuh per baris.
 */
class RecruitmentInterviewExportPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentDivision $dataDivision;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->dataDivision = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create(['created_by' => $this->staff->id]);

        $this->session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    public function test_export_writes_every_page_in_registration_number_order(): void
    {
        config(['recruitment.interview_export_page_size' => 2]);

        $registrationNumbers = [
            'OPREC-2026-00004',
            'OPREC-2026-00002',
            'OPREC-2026-00005',
            'OPREC-2026-00001',
            'OPREC-2026-00003',
        ];

        foreach ($registrationNumbers as $registrationNumber) {
            $application = $this->makeApplication($registrationNumber);
            $this->makeInterview($application, RecruitmentInterview::KIND_PRIMARY);
        }

        $rows = $this->exportRows(['columns' => ['registration_number']]);

        self::assertCount(5, $rows);

        $exported = array_map(fn (array $row): string => $row[0], $rows);

        self::assertSame([
            'OPREC-2026-00001',
            'OPREC-2026-00002',
            'OPREC-2026-00003',
            'OPREC-2026-00004',
            'OPREC-2026-00005',
        ], $exported);
        self::assertCount(5, array_unique($exported));
    }

    public function test_export_orders_primary_interview_before_secondary_within_application(): void
    {
        config(['recruitment.interview_export_page_size' => 2]);

        $application = $this->makeApplication('OPREC-2026-00001');
        $this->makeInterview($application, RecruitmentInterview::KIND_SECONDARY);
        $this->makeInterview($application, RecruitmentInterview::KIND_PRIMARY);

        $rows = $this->exportRows(['columns' => ['registration_number', 'interview_kind']]);

        self::assertSame([
            ['OPREC-2026-00001', 'Primer'],
            ['OPREC-2026-00001', 'Sekunder'],
        ], $rows);
    }

    public function test_export_scope_filter_applies_across_page_boundaries(): void
    {
        config(['recruitment.interview_export_page_size' => 2]);

        $evaluatedOne = $this->makeApplication('OPREC-2026-00001');
        $this->makeInterview($evaluatedOne, RecruitmentInterview::KIND_PRIMARY, withEvaluation: true);

        $pendingOne = $this->makeApplication('OPREC-2026-00002');
        $this->makeInterview($pendingOne, RecruitmentInterview::KIND_PRIMARY);

        $evaluatedTwo = $this->makeApplication('OPREC-2026-00003');
        $this->makeInterview($evaluatedTwo, RecruitmentInterview::KIND_PRIMARY, withEvaluation: true);

        $evaluatedThree = $this->makeApplication('OPREC-2026-00004');
        $this->makeInterview($evaluatedThree, RecruitmentInterview::KIND_PRIMARY, withEvaluation: true);

        $pendingTwo = $this->makeApplication('OPREC-2026-00005');
        $this->makeInterview($pendingTwo, RecruitmentInterview::KIND_PRIMARY);

        $evaluatedRows = $this->exportRows(['scope' => 'evaluated', 'columns' => ['registration_number']]);
        $pendingRows = $this->exportRows(['scope' => 'pending', 'columns' => ['registration_number']]);

        self::assertSame(
            ['OPREC-2026-00001', 'OPREC-2026-00003', 'OPREC-2026-00004'],
            array_map(fn (array $row): string => $row[0], $evaluatedRows)
        );
        self::assertSame(
            ['OPREC-2026-00002', 'OPREC-2026-00005'],
            array_map(fn (array $row): string => $row[0], $pendingRows)
        );
    }

    public function test_export_paginates_with_keyset_not_offset(): void
    {
        config(['recruitment.interview_export_page_size' => 2]);

        foreach ([
            'OPREC-2026-00001',
            'OPREC-2026-00002',
            'OPREC-2026-00003',
            'OPREC-2026-00004',
            'OPREC-2026-00005',
            'OPREC-2026-00006',
            'OPREC-2026-00007',
            'OPREC-2026-00008',
        ] as $registrationNumber) {
            $application = $this->makeApplication($registrationNumber);
            $this->makeInterview($application, RecruitmentInterview::KIND_PRIMARY);
        }

        $queries = $this->exportQueries();

        foreach ($queries as $sql) {
            self::assertStringNotContainsString('offset', strtolower($sql));
        }

        // 8 aplikasi / 2 per halaman = 4 halaman berisi + 1 halaman kosong;
        // chunk(200) lama hanya menjalankan 1 chunk sehingga jumlah query-nya
        // tetap ±14 dan tidak mungkin mencapai floor ini.
        self::assertGreaterThan(40, count($queries));
    }

    public function test_export_does_not_duplicate_or_skip_rows_at_exact_page_multiple(): void
    {
        config(['recruitment.interview_export_page_size' => 2]);

        foreach (['OPREC-2026-00001', 'OPREC-2026-00002', 'OPREC-2026-00003', 'OPREC-2026-00004'] as $registrationNumber) {
            $application = $this->makeApplication($registrationNumber);
            $this->makeInterview($application, RecruitmentInterview::KIND_PRIMARY);
        }

        $rows = $this->exportRows(['columns' => ['registration_number']]);

        $exported = array_map(fn (array $row): string => $row[0], $rows);

        self::assertSame([
            'OPREC-2026-00001',
            'OPREC-2026-00002',
            'OPREC-2026-00003',
            'OPREC-2026-00004',
        ], $exported);
        self::assertCount(4, array_unique($exported));
    }

    public function test_export_advances_cursor_when_a_page_emits_no_rows(): void
    {
        config(['recruitment.interview_export_page_size' => 2]);

        $firstPending = $this->makeApplication('OPREC-2026-00001');
        $this->makeInterview($firstPending, RecruitmentInterview::KIND_PRIMARY);

        $secondPending = $this->makeApplication('OPREC-2026-00002');
        $this->makeInterview($secondPending, RecruitmentInterview::KIND_PRIMARY);

        $firstEvaluated = $this->makeApplication('OPREC-2026-00003');
        $this->makeInterview($firstEvaluated, RecruitmentInterview::KIND_PRIMARY, withEvaluation: true);

        $secondEvaluated = $this->makeApplication('OPREC-2026-00004');
        $this->makeInterview($secondEvaluated, RecruitmentInterview::KIND_PRIMARY, withEvaluation: true);

        $rows = $this->exportRows(['scope' => 'evaluated', 'columns' => ['registration_number']]);

        self::assertSame(
            ['OPREC-2026-00003', 'OPREC-2026-00004'],
            array_map(fn (array $row): string => $row[0], $rows)
        );
    }

    public function test_export_does_not_lazy_load_any_relation(): void
    {
        $evaluated = $this->makeApplication('OPREC-2026-00001');
        $this->makeInterview($evaluated, RecruitmentInterview::KIND_PRIMARY, withEvaluation: true);

        $pending = $this->makeApplication('OPREC-2026-00002');
        $this->makeInterview($pending, RecruitmentInterview::KIND_PRIMARY);

        $decided = $this->makeApplication('OPREC-2026-00003');
        $this->makeInterview($decided, RecruitmentInterview::KIND_PRIMARY, withEvaluation: true);

        RecruitmentAttendance::query()->create([
            'recruitment_application_id' => $decided->id,
            'recruitment_interview_session_id' => $this->session->id,
            'method' => AttendanceMethod::RegistrationNumber,
            'checked_in_at' => now(),
            'checked_in_by' => $this->staff->id,
        ]);

        RecruitmentFinalDecision::query()->create([
            'recruitment_application_id' => $decided->id,
            'membership_type' => 'member',
            'final_division_id' => $this->dataDivision->id,
            'decided_by' => $this->staff->id,
            'decided_at' => now(),
        ]);

        $this->staff->loadMissing(['roles', 'roles.permissions', 'permissions']);

        Model::preventLazyLoading(true);

        try {
            $response = $this->actingAs($this->staff)
                ->get(route('dashboard.recruitment.periods.interviews.export', $this->period));

            $response->assertOk();

            $content = $response->streamedContent();
            self::assertIsString($content);
            self::assertStringContainsString('OPREC-2026-00001', $content);
            self::assertStringContainsString('OPREC-2026-00002', $content);
            // Kolom yang hanya terisi lewat relasi attendance/finalDecision/session.division.
            self::assertStringContainsString(AttendanceMethod::RegistrationNumber->label(), $content);
            self::assertStringContainsString($this->dataDivision->name, $content);
            self::assertStringContainsString($this->programming->name, $content);
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_export_query_count_stays_flat_within_one_page(): void
    {
        $first = $this->makeApplication('OPREC-2026-00001');
        $this->makeInterview($first, RecruitmentInterview::KIND_PRIMARY, withEvaluation: true);

        $second = $this->makeApplication('OPREC-2026-00002');
        $this->makeInterview($second, RecruitmentInterview::KIND_PRIMARY);

        $twoRows = $this->countExportQueries();

        foreach (['OPREC-2026-00003', 'OPREC-2026-00004', 'OPREC-2026-00005', 'OPREC-2026-00006'] as $registrationNumber) {
            $application = $this->makeApplication($registrationNumber);
            $this->makeInterview($application, RecruitmentInterview::KIND_PRIMARY);
        }

        $sixRows = $this->countExportQueries();

        self::assertSame($twoRows, $sixRows);
        self::assertLessThan(25, $twoRows);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<list<string>>
     */
    private function exportRows(array $query = []): array
    {
        $response = $this->actingAs($this->staff)
            ->get(route('dashboard.recruitment.periods.interviews.export', [$this->period, ...$query]));

        $response->assertOk();

        $content = $response->streamedContent();
        self::assertIsString($content);

        $lines = array_values(array_filter(
            array_map(fn (string $line): string => trim($line), explode("\n", trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $content)))),
            fn (string $line): bool => $line !== ''
        ));

        return array_values(array_map(
            fn (string $line): array => array_map(strval(...), str_getcsv($line)),
            array_slice($lines, 1)
        ));
    }

    /**
     * @return list<string>
     */
    private function exportQueries(): array
    {
        DB::enableQueryLog();

        try {
            $this->actingAs($this->staff)
                ->get(route('dashboard.recruitment.periods.interviews.export', $this->period))
                ->assertOk()
                ->streamedContent();

            return array_values(array_map(
                fn (array $entry): string => (string) ($entry['query'] ?? ''),
                DB::getQueryLog()
            ));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function countExportQueries(): int
    {
        DB::enableQueryLog();

        try {
            $this->actingAs($this->staff)
                ->get(route('dashboard.recruitment.periods.interviews.export', $this->period))
                ->assertOk()
                ->streamedContent();

            $count = 0;

            foreach (DB::getQueryLog() as $entry) {
                $sql = (string) ($entry['query'] ?? '');

                if (str_contains($sql, 'recruitment_') || str_contains($sql, 'users')) {
                    $count++;
                }
            }

            return $count;
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function makeApplication(string $registrationNumber): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'registration_number' => $registrationNumber,
            'primary_division_id' => $this->programming->id,
            'full_name' => 'Pagination '.$registrationNumber,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);
    }

    private function makeInterview(
        RecruitmentApplication $application,
        string $interviewKind,
        bool $withEvaluation = false
    ): RecruitmentInterview {
        $interview = RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $this->session->id,
            'interview_kind' => $interviewKind,
            'interviewer_id' => $this->staff->id,
            'scheduled_at' => now(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => $withEvaluation ? InterviewStatus::Completed : InterviewStatus::Waiting,
        ]);

        if ($withEvaluation) {
            RecruitmentEvaluation::query()->create([
                'recruitment_application_id' => $application->id,
                'recruitment_interview_id' => $interview->id,
                'speaking_score' => 8,
                'technical_score' => 9,
                'attitude_score' => 8,
                'recommendation' => EvaluationRecommendation::Recommended,
                'notes' => 'Catatan evaluasi pagination.',
                'save_count' => 1,
                'evaluated_by' => $this->staff->id,
                'evaluated_at' => now(),
            ]);
        }

        return $interview;
    }
}

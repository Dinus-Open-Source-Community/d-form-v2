<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Export laporan recruitment wajib berhenti membangun section laporan yang
 * tidak dipakai. Test ini mengunci perilaku tersebut lewat jumlah statement
 * per tabel domain dan membandingkan isi CSV dengan baseline eksplisit.
 */
class RecruitmentReportExportQueryTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private User $admin;

    private RecruitmentDivision $programming;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo('recruitment.reports.export');

        $this->period = RecruitmentPeriod::factory()->create();
        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
    }

    public function test_export_funnel_hanya_menjalankan_query_funnel(): void
    {
        $this->makeApplication(ApplicationStage::Submitted);
        $this->makeApplication(ApplicationStage::Completed, ApplicationResult::Accepted);

        $statements = $this->captureQueries(function (): void {
            $this->getExport('dashboard.recruitment.reports.export.funnel');
        });

        $domainStatements = $this->domainStatements($statements);

        $this->assertCount(3, $domainStatements);
        $this->assertSame(1, $this->countStatements($domainStatements, 'recruitment_periods'));
        $this->assertSame(1, $this->countStatements($domainStatements, 'group by stage'));
        $this->assertSame(1, $this->countStatements($domainStatements, 'group by result'));
        $this->assertSame(0, $this->countStatements($domainStatements, 'recruitment_interviews'));
        $this->assertSame(0, $this->countStatements($domainStatements, 'recruitment_feedbacks'));
        $this->assertSame(0, $this->countStatements($domainStatements, 'recruitment_divisions'));
        $this->assertSame(0, $this->countStatements($domainStatements, 'group by semester'));
    }

    public function test_export_funnel_csv_sama_dengan_hasil_build_funnel(): void
    {
        $this->makeApplication(ApplicationStage::Submitted);
        $this->makeApplication(ApplicationStage::Completed, ApplicationResult::Accepted);
        $this->makeApplication(ApplicationStage::Completed, ApplicationResult::Rejected);

        $content = $this->getExport('dashboard.recruitment.reports.export.funnel');

        $expected = "\xEF\xBB\xBFstage,label,count\n"
            ."submitted,\"Pendaftaran diterima\",1\n"
            ."screening,Screening,0\n"
            ."interview,Interview,0\n"
            ."final_review,\"Final review\",0\n"
            ."completed,Selesai,2\n"
            ."accepted,Diterima,1\n"
            ."rejected,\"Tidak diterima\",1\n";

        $this->assertSame($expected, $content);
    }

    public function test_export_funnel_periode_tidak_ada_404(): void
    {
        $this->makeApplication(ApplicationStage::Submitted, ApplicationResult::Pending);

        $this->assertSame(404, $this->exportStatus(
            'dashboard.recruitment.reports.export.funnel',
            '00000000-0000-0000-0000-000000000000'
        ));
    }

    public function test_export_applicants_tidak_menjalankan_section_laporan_lain(): void
    {
        $this->makeApplication(ApplicationStage::Interview);

        $statements = $this->captureQueries(function (): void {
            $this->getExport('dashboard.recruitment.reports.export.applicants');
        });

        $domainStatements = $this->domainStatements($statements);

        $this->assertSame(1, $this->countStatements($domainStatements, 'recruitment_periods'));
        $this->assertSame(0, $this->countStatements($domainStatements, 'recruitment_interviews'));
        $this->assertSame(0, $this->countStatements($domainStatements, 'recruitment_feedbacks'));
        $this->assertSame(0, $this->countStatements($domainStatements, 'group by'));
    }

    public function test_export_applicants_csv_sama_dengan_baseline(): void
    {
        $this->makeApplication(ApplicationStage::Completed, ApplicationResult::Accepted, null, [
            'registration_number' => 'OPREC-2026-00002',
            'full_name' => 'Budi Santoso',
            'nim' => 'A11.2026.00002',
            'semester' => 3,
            'submitted_at' => '2026-02-03 04:05:06',
        ]);

        $this->makeApplication(ApplicationStage::Submitted, ApplicationResult::Pending, null, [
            'registration_number' => 'OPREC-2026-00001',
            'full_name' => 'Ani Wijaya',
            'nim' => 'A11.2026.00001',
            'semester' => 1,
            'secondary_division_id' => RecruitmentDivision::query()->where('code', 'data')->value('id'),
            'submitted_at' => '2026-01-02 03:04:05',
        ]);

        $content = $this->getExport('dashboard.recruitment.reports.export.applicants');

        $expected = "\xEF\xBB\xBFregistration_number,full_name,nim,semester,primary_division,secondary_division,stage,result,submitted_at\n"
            ."OPREC-2026-00001,\"Ani Wijaya\",A11.2026.00001,1,\"Pemrograman / Programming\",Data,submitted,pending,\"2026-01-02 03:04:05\"\n"
            ."OPREC-2026-00002,\"Budi Santoso\",A11.2026.00002,3,\"Pemrograman / Programming\",,completed,accepted,\"2026-02-03 04:05:06\"\n";

        $this->assertSame($expected, $content);
    }

    public function test_export_applicants_records_pii_free_activity_audit(): void
    {
        $application = $this->makeApplication(ApplicationStage::Interview, attributes: [
            'full_name' => 'Sensitive Applicant Name',
            'nim' => 'SENSITIVE-NIM-123',
            'personal_email' => 'sensitive.applicant@example.test',
        ]);

        $this->getExport('dashboard.recruitment.reports.export.applicants');

        $audit = RecruitmentActivityLog::query()
            ->where('action', 'export.generated')
            ->where('entity_type', 'recruitment_export')
            ->firstOrFail();

        self::assertSame($this->admin->id, $audit->actor_id);
        self::assertSame($this->period->id, $audit->recruitment_period_id);
        self::assertSame([
            'scope' => 'applicants',
            'rows_generated' => 1,
        ], $audit->new_values);
        self::assertNotNull($audit->created_at);

        $auditPayload = json_encode($audit->getAttributes(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($application->full_name, $auditPayload);
        self::assertStringNotContainsString($application->nim, $auditPayload);
        self::assertStringNotContainsString($application->personal_email, $auditPayload);
    }

    public function test_export_applicants_memakai_keyset_tanpa_offset(): void
    {
        for ($index = 1; $index <= 501; $index++) {
            $this->makeApplication(ApplicationStage::Submitted, ApplicationResult::Pending, null, [
                'registration_number' => sprintf('OPREC-2026-%05d', $index),
                'nim' => sprintf('A11.2026.%05d', $index),
            ]);
        }

        /** @var list<array{sql: string, bindings: array<int, mixed>}> $statements */
        $statements = [];

        DB::listen(function (QueryExecuted $query) use (&$statements): void {
            $statements[] = [
                'sql' => str_replace(['`', '"'], '', strtolower($query->sql)),
                'bindings' => $query->bindings,
            ];
        });

        $content = $this->getExport('dashboard.recruitment.reports.export.applicants');

        $lines = explode("\n", rtrim($content, "\n"));

        $this->assertCount(502, $lines);
        $this->assertSame(
            "\xEF\xBB\xBFregistration_number,full_name,nim,semester,primary_division,secondary_division,stage,result,submitted_at",
            $lines[0]
        );

        $registrationNumbers = array_map(
            static fn (string $line): string => (string) str_getcsv($line, ',', '"', '')[0],
            array_slice($lines, 1)
        );

        $this->assertSame(
            array_map(static fn (int $index): string => sprintf('OPREC-2026-%05d', $index), range(1, 501)),
            $registrationNumbers
        );

        $applicantStatements = array_values(array_filter(
            $statements,
            static fn (array $statement): bool => str_contains($statement['sql'], 'recruitment_applications')
        ));

        // 501 baris = dua halaman keyset 500; tidak ada OFFSET sama sekali.
        $this->assertCount(2, $applicantStatements);

        foreach ($applicantStatements as $statement) {
            $this->assertStringNotContainsString(' offset ', $statement['sql']);
            $this->assertStringContainsString('limit 500', $statement['sql']);
            $this->assertStringContainsString('registration_number > ?', $statement['sql']);
            $this->assertStringContainsString('deleted_at is null', $statement['sql']);
        }

        $this->assertSame('', $applicantStatements[0]['bindings'][1]);
        $this->assertSame('OPREC-2026-00500', $applicantStatements[1]['bindings'][1]);
    }

    public function test_export_applicants_division_terhapus_menghasilkan_kolom_kosong(): void
    {
        $deletedPrimary = RecruitmentDivision::factory()->create(['name' => 'Divisi Primary Terhapus']);
        $deletedSecondary = RecruitmentDivision::factory()->create(['name' => 'Divisi Secondary Terhapus']);
        $deletedPrimary->delete();
        $deletedSecondary->delete();

        $this->makeApplication(ApplicationStage::Submitted, ApplicationResult::Pending, null, [
            'primary_division_id' => $deletedPrimary->id,
            'secondary_division_id' => $deletedSecondary->id,
        ]);

        $content = $this->getExport('dashboard.recruitment.reports.export.applicants');
        $lines = explode("\n", rtrim($content, "\n"));

        $this->assertCount(2, $lines);

        $cells = str_getcsv($lines[1], ',', '"', '');

        $this->assertSame('', $cells[4]);
        $this->assertSame('', $cells[5]);
        $this->assertStringNotContainsString('Divisi Primary Terhapus', $content);
        $this->assertStringNotContainsString('Divisi Secondary Terhapus', $content);
    }

    public function test_export_applicants_periode_tidak_ada_404(): void
    {
        $this->assertSame(404, $this->exportStatus(
            'dashboard.recruitment.reports.export.applicants',
            '00000000-0000-0000-0000-000000000000'
        ));
    }

    public function test_export_periode_lain_ditolak_untuk_user_tanpa_izin_periode(): void
    {
        $staff = $this->staffUser();
        $foreignPeriod = RecruitmentPeriod::factory()->create(['created_by' => $this->admin->id]);
        $this->makeApplication(ApplicationStage::Submitted, ApplicationResult::Pending, $foreignPeriod);

        $this->assertTrue($staff->can('recruitment.reports.export'));
        $this->assertTrue($staff->can('recruitment.applications.export'));
        $this->assertFalse($staff->can('recruitment.periods.view'));

        foreach (['funnel', 'applicants'] as $endpoint) {
            $this->actingAs($staff)
                ->get(route("dashboard.recruitment.reports.export.{$endpoint}", ['period_id' => $foreignPeriod->id]))
                ->assertForbidden();
        }
    }

    public function test_export_periode_default_ditolak_untuk_user_tanpa_izin_periode(): void
    {
        $staff = $this->staffUser();
        $this->makeApplication(ApplicationStage::Submitted, ApplicationResult::Pending);

        $this->assertFalse($staff->can('recruitment.periods.view'));

        foreach (['funnel', 'applicants'] as $endpoint) {
            $this->actingAs($staff)
                ->get(route("dashboard.recruitment.reports.export.{$endpoint}"))
                ->assertForbidden();
        }
    }

    public function test_export_applicants_ditolak_tanpa_izin_export_aplikasi(): void
    {
        $reportOnly = User::factory()->create();
        $reportOnly->givePermissionTo([
            'recruitment.dashboard.view',
            'recruitment.reports.export',
            'recruitment.periods.view',
        ]);

        $this->assertTrue($reportOnly->can('recruitment.reports.export'));
        $this->assertTrue($reportOnly->can('recruitment.periods.view'));
        $this->assertFalse($reportOnly->can('recruitment.applications.export'));

        $this->actingAs($reportOnly)
            ->get(route('dashboard.recruitment.reports.export.applicants', ['period_id' => $this->period->id]))
            ->assertForbidden();

        // Funnel bersifat agregat dan tidak memuat PII: tetap boleh.
        $this->actingAs($reportOnly)
            ->get(route('dashboard.recruitment.reports.export.funnel', ['period_id' => $this->period->id]))
            ->assertOk();
    }

    public function test_export_diizinkan_untuk_admin_dengan_izin_periode_dan_aplikasi(): void
    {
        $this->makeApplication(ApplicationStage::Submitted, ApplicationResult::Pending);

        $this->assertTrue($this->admin->can('recruitment.periods.view'));
        $this->assertTrue($this->admin->can('recruitment.applications.export'));

        $funnel = $this->getExport('dashboard.recruitment.reports.export.funnel');
        $applicants = $this->getExport('dashboard.recruitment.reports.export.applicants');

        $this->assertStringStartsWith("\xEF\xBB\xBFstage,label,count\n", $funnel);
        $this->assertStringStartsWith("\xEF\xBB\xBFregistration_number,full_name,nim,", $applicants);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeApplication(
        ApplicationStage $stage,
        ApplicationResult $result = ApplicationResult::Pending,
        ?RecruitmentPeriod $period = null,
        array $attributes = [],
    ): RecruitmentApplication {
        return RecruitmentApplication::factory()->create(array_merge([
            'recruitment_period_id' => ($period ?? $this->period)->id,
            'primary_division_id' => $this->programming->id,
            'stage' => $stage,
            'result' => $result,
        ], $attributes));
    }

    private function getExport(string $routeName, ?string $periodId = null): string
    {
        return $this->actingAs($this->admin)
            ->get(route($routeName, ['period_id' => $periodId ?? $this->period->id]))
            ->assertOk()
            ->streamedContent();
    }

    private function exportStatus(string $routeName, ?string $periodId = null): int
    {
        return $this->actingAs($this->admin)
            ->get(route($routeName, ['period_id' => $periodId ?? $this->period->id]))
            ->getStatusCode();
    }

    /** Role `recruitment-staff`: punya reports.export + applications.export, tanpa periods.view. */
    private function staffUser(): User
    {
        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        return $staff;
    }

    /**
     * @param  callable(): void  $callback
     * @return list<string>
     */
    private function captureQueries(callable $callback): array
    {
        /** @var list<string> $statements */
        $statements = [];

        DB::listen(function (QueryExecuted $query) use (&$statements): void {
            $statements[] = str_replace(['`', '"'], '', strtolower($query->sql));
        });

        $callback();

        return $statements;
    }

    /**
     * @param  list<string>  $statements
     * @return list<string>
     */
    private function domainStatements(array $statements): array
    {
        return array_values(array_filter(
            $statements,
            static fn (string $sql): bool => str_contains($sql, 'recruitment_')
        ));
    }

    /**
     * @param  list<string>  $statements
     */
    private function countStatements(array $statements, string $needle): int
    {
        return count(array_filter(
            $statements,
            static fn (string $sql): bool => str_contains($sql, $needle)
        ));
    }
}

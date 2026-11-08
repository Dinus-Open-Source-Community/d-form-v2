<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Services\Recruitment\RecruitmentApplicationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Query daftar pelamar (BE-P2-001): kolom selektif, index komposit
 * (period + submitted_at), filter lengkap, dan paginasi deterministik.
 * Pencarian tetap memakai LIKE: FULLTEXT ditolak karena table-wide dan tidak
 * online di MariaDB (lihat laporan review BE-P2-001 F1/F2).
 */
class RecruitmentApplicationListQueryTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $primaryDivision;

    private RecruitmentDivision $secondaryDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = RecruitmentPeriod::factory()->create();
        $this->primaryDivision = RecruitmentDivision::factory()->create();
        $this->secondaryDivision = RecruitmentDivision::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function application(array $attributes = []): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->primaryDivision->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'revision_required' => false,
            'submitted_at' => now(),
            ...$attributes,
        ]);
    }

    private function service(): RecruitmentApplicationService
    {
        return app(RecruitmentApplicationService::class);
    }

    public function test_composite_indexes_exist_with_expected_column_order(): void
    {
        $indexes = collect(Schema::getIndexes('recruitment_applications'))->keyBy('name');

        $this->assertSame(
            ['recruitment_period_id', 'submitted_at'],
            $indexes['rec_apps_period_submitted_idx']['columns'],
        );
        $this->assertSame(
            ['recruitment_period_id', 'stage', 'submitted_at'],
            $indexes['rec_apps_period_stage_submitted_idx']['columns'],
        );
        $this->assertSame(
            ['recruitment_period_id', 'semester', 'submitted_at'],
            $indexes['rec_apps_period_semester_submitted_idx']['columns'],
        );
    }

    public function test_list_query_selects_only_columns_needed_by_the_list_page(): void
    {
        $submittedAt = now()->subHour()->startOfSecond();

        $application = $this->application([
            'full_name' => 'Budi Santoso',
            'nim' => 'A11.2021.00001',
            'semester' => 3,
            'revision_required' => true,
            'submitted_at' => $submittedAt,
            'primary_division_id' => $this->primaryDivision->id,
            'secondary_division_id' => $this->secondaryDivision->id,
        ]);

        $row = $this->service()->paginate(['period_id' => $this->period->id])->items()[0];
        $mapped = $this->service()->toListArray($row);

        $this->assertSame($application->id, $mapped['id']);
        $this->assertSame($application->registration_number, $mapped['registration_number']);
        $this->assertSame('Budi Santoso', $mapped['full_name']);
        $this->assertSame('A11.2021.00001', $mapped['nim']);
        $this->assertSame(3, $mapped['semester']);
        $this->assertSame(ApplicationStage::Submitted->value, $mapped['stage']);
        $this->assertSame(ApplicationStage::Submitted->label(), $mapped['stage_label']);
        $this->assertSame(ApplicationResult::Pending->value, $mapped['result']);
        $this->assertSame(ApplicationResult::Pending->label(), $mapped['result_label']);
        $this->assertTrue($mapped['revision_required']);
        $this->assertSame($submittedAt->toIso8601String(), $mapped['submitted_at']);
        $this->assertSame(
            ['id' => $this->primaryDivision->id, 'name' => $this->primaryDivision->name],
            $mapped['primary_division'],
        );
        $this->assertSame(
            ['id' => $this->secondaryDivision->id, 'name' => $this->secondaryDivision->name],
            $mapped['secondary_division'],
        );
        $this->assertSame(
            ['id' => $this->period->id, 'name' => $this->period->name],
            $mapped['period'],
        );

        // Kolom berat tidak boleh ikut terhidrasi oleh SELECT daftar.
        $loaded = $row->getAttributes();
        $this->assertArrayNotHasKey('phone', $loaded);
        $this->assertArrayNotHasKey('personal_email', $loaded);
        $this->assertArrayNotHasKey('student_email', $loaded);
        $this->assertArrayNotHasKey('instagram_username', $loaded);
    }

    public function test_stage_filter_returns_only_matching_stage(): void
    {
        $interview = $this->application(['stage' => ApplicationStage::Interview]);
        $this->application(['stage' => ApplicationStage::Submitted]);

        $paginator = $this->service()->paginate([
            'period_id' => $this->period->id,
            'stage' => ApplicationStage::Interview->value,
        ]);

        $this->assertSame(1, $paginator->total());
        $this->assertSame([$interview->id], $paginator->getCollection()->pluck('id')->all());
    }

    public function test_screening_queue_excludes_revision_and_decided_applications(): void
    {
        $submitted = $this->application(['stage' => ApplicationStage::Submitted]);
        $screening = $this->application(['stage' => ApplicationStage::Screening]);
        $this->application(['stage' => ApplicationStage::Screening, 'revision_required' => true]);
        $this->application(['stage' => ApplicationStage::Screening, 'result' => ApplicationResult::Accepted]);
        $this->application(['stage' => ApplicationStage::Interview]);

        $paginator = $this->service()->paginate([
            'period_id' => $this->period->id,
            'queue' => 'screening',
        ]);

        $this->assertEqualsCanonicalizing(
            [$submitted->id, $screening->id],
            $paginator->getCollection()->pluck('id')->all(),
        );
    }

    public function test_semester_filter_returns_only_matching_semester(): void
    {
        $third = $this->application(['semester' => 3]);
        $this->application(['semester' => 5]);

        $paginator = $this->service()->paginate([
            'period_id' => $this->period->id,
            'semester' => '3',
        ]);

        $this->assertSame([$third->id], $paginator->getCollection()->pluck('id')->all());
    }

    public function test_division_filter_matches_secondary_division(): void
    {
        $secondary = $this->application(['secondary_division_id' => $this->secondaryDivision->id]);
        $this->application(['primary_division_id' => $this->primaryDivision->id]);

        $paginator = $this->service()->paginate([
            'period_id' => $this->period->id,
            'division_id' => $this->secondaryDivision->id,
        ]);

        $this->assertSame([$secondary->id], $paginator->getCollection()->pluck('id')->all());
    }

    public function test_search_matches_name_and_nim_substring_with_like(): void
    {
        $budi = $this->application(['full_name' => 'Budi Santoso', 'nim' => 'A11.2021.00001']);
        $this->application(['full_name' => 'Siti Aminah', 'nim' => 'A11.2022.00002']);

        $byName = $this->service()->paginate([
            'period_id' => $this->period->id,
            'search' => 'Budi',
        ]);

        $this->assertSame(1, $byName->total());
        $this->assertSame([$budi->id], $byName->getCollection()->pluck('id')->all());

        // Awalan NIM: FULLTEXT dulu lambat di sini; LIKE tetap substring biasa.
        $byNim = $this->service()->paginate([
            'period_id' => $this->period->id,
            'search' => 'A11.2021',
        ]);

        $this->assertSame(1, $byNim->total());
        $this->assertSame([$budi->id], $byNim->getCollection()->pluck('id')->all());
    }

    public function test_pagination_is_deterministic_when_submitted_at_ties(): void
    {
        $submittedAt = now()->subHour();

        for ($index = 0; $index < 25; $index++) {
            $this->application([
                'full_name' => 'Pelamar '.$index,
                'submitted_at' => $submittedAt,
            ]);
        }

        $firstPage = $this->service()->paginate(['period_id' => $this->period->id], 1, 20);
        $secondPage = $this->service()->paginate(['period_id' => $this->period->id], 2, 20);

        $this->assertCount(20, $firstPage->items());
        $this->assertCount(5, $secondPage->items());

        $ids = [...$firstPage->getCollection()->pluck('id')->all(), ...$secondPage->getCollection()->pluck('id')->all()];
        $this->assertCount(25, array_unique($ids));

        // Urutan identik dengan sumbernya: submitted_at sama, jadi id DESC.
        $expected = RecruitmentApplication::query()
            ->where('recruitment_period_id', $this->period->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        $this->assertSame($expected, $ids);
    }

    /**
     * Penjaga performa: query daftar yang benar-benar dikirim `paginate()` harus
     * memakai index komposit dan backward index scan, tanpa filesort. Read-only
     * pada koneksi `mysql`; dilewati bila koneksi/index/data MySQL tidak tersedia.
     */
    public function test_list_query_uses_composite_index_and_backward_scan_on_mysql(): void
    {
        // phpunit.xml memaksa DB_DATABASE=:memory: untuk koneksi default (SQLite);
        // koneksi `mysql` tetap memakai nama database asli dari environment proses.
        $database = getenv('DB_DATABASE');

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            $this->markTestSkipped('Nama database MySQL tidak tersedia di environment.');
        }

        config(['database.connections.mysql.database' => $database]);
        DB::purge('mysql');

        try {
            $connection = DB::connection('mysql');
            $connection->getPdo();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Koneksi MySQL tidak tersedia: '.$exception->getMessage());
        }

        if (! Schema::connection('mysql')->hasTable('recruitment_applications')) {
            $this->markTestSkipped('Tabel recruitment_applications tidak ada di MySQL.');
        }

        $indexNames = collect(Schema::connection('mysql')->getIndexes('recruitment_applications'))
            ->pluck('name')
            ->all();

        if (! in_array('rec_apps_period_submitted_idx', $indexNames, true)) {
            $this->markTestSkipped('Index rec_apps_period_submitted_idx belum dibuat di MySQL.');
        }

        $periodId = $connection->table('recruitment_applications')->value('recruitment_period_id');

        if ($periodId === null) {
            $this->markTestSkipped('Tabel recruitment_applications kosong di MySQL.');
        }

        $captured = [];
        $connection->listen(static function (QueryExecuted $query) use (&$captured): void {
            $captured[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
        });

        // Jalankan service pada koneksi mysql agar SQL yang diuji adalah SQL asli
        // yang dikirim paginate(), bukan SELECT tulis tangan yang bisa drift.
        $previousDefault = DB::getDefaultConnection();
        DB::setDefaultConnection('mysql');

        try {
            $this->service()->paginate(['period_id' => $periodId], 1, 20);
        } finally {
            DB::setDefaultConnection($previousDefault);
        }

        $listQuery = collect($captured)->first(
            static fn (array $query): bool => str_starts_with($query['sql'], 'select ')
                && str_contains($query['sql'], 'order by'),
        );

        $this->assertIsArray($listQuery, 'Query daftar tidak tertangkap dari paginate().');

        // Kolom eksplisit LIST_COLUMNS: 12 kolom tanpa kolom berat.
        preg_match('/^select (.*?) from /is', $listQuery['sql'], $matches);
        $this->assertArrayHasKey(1, $matches);
        $this->assertCount(12, explode(', ', $matches[1]));
        $this->assertStringNotContainsString('`phone`', $listQuery['sql']);
        $this->assertStringNotContainsString('`personal_email`', $listQuery['sql']);

        $explainSql = $connection->getQueryGrammar()
            ->substituteBindingsIntoRawSql($listQuery['sql'], $listQuery['bindings']);

        $step = collect($connection->select('EXPLAIN '.$explainSql))
            ->map(static fn (object $row): array => (array) $row)
            ->first(static fn (array $row): bool => ($row['table'] ?? null) === 'recruitment_applications');

        $this->assertIsArray($step, 'EXPLAIN tidak memuat baris recruitment_applications.');

        $extra = (string) ($step['Extra'] ?? $step['extra'] ?? '');

        $this->assertSame('rec_apps_period_submitted_idx', $step['key'] ?? $step['Key'] ?? null);
        $this->assertStringContainsStringIgnoringCase('backward', $extra);
        $this->assertStringNotContainsStringIgnoringCase('using filesort', $extra);
    }
}

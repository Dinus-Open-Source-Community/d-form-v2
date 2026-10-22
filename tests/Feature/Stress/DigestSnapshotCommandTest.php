<?php

namespace Tests\Feature\Stress;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;
use Throwable;

/**
 * Menguji `stress:digests` terhadap MySQL sungguhan, karena performance_schema
 * tidak ada di koneksi sqlite bawaan phpunit.
 */
class DigestSnapshotCommandTest extends TestCase
{
    private string $previousDefaultConnection;

    /** @var array<string,mixed> Konfigurasi koneksi mysql sebelum test mengubahnya. */
    private array $previousMysqlConfig = [];

    private string $snapshotPath = '';

    private string $diffPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDefaultConnection = DB::getDefaultConnection();
        $previousMysqlConfig = config('database.connections.mysql');
        $this->previousMysqlConfig = is_array($previousMysqlConfig) ? $previousMysqlConfig : [];
        $this->useMysqlConnection();

        $this->snapshotPath = storage_path('app/stress/digests-test.json');
        $this->diffPath = storage_path('app/stress/digests-diff.json');

        $this->deleteArtifactFiles();
    }

    protected function tearDown(): void
    {
        $this->deleteArtifactFiles();

        DB::setDefaultConnection($this->previousDefaultConnection);
        config(['database.connections.mysql' => $this->previousMysqlConfig]);
        DB::purge('mysql');

        parent::tearDown();
    }

    public function test_snapshot_writes_expected_json_shape(): void
    {
        $this->artisan('stress:digests', ['--snapshot' => $this->snapshotPath])->assertSuccessful();

        $this->assertFileExists($this->snapshotPath);

        $data = json_decode((string) file_get_contents($this->snapshotPath), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('captured_at', $data);
        $this->assertArrayHasKey('schema', $data);
        $this->assertSame(DB::connection()->getDatabaseName(), $data['schema']);
        $this->assertIsArray($data['digests']);
        $this->assertNotEmpty($data['digests']);

        foreach ($data['digests'] as $digest) {
            $this->assertIsArray($digest);

            foreach ([
                'digest',
                'digest_text',
                'count_star',
                'sum_timer_wait',
                'sum_rows_examined',
                'sum_rows_sent',
                'sum_no_index_used',
                'sum_created_tmp_tables',
            ] as $key) {
                $this->assertArrayHasKey($key, $digest);
            }

            $this->assertIsInt($digest['count_star']);
            $this->assertIsInt($digest['sum_timer_wait']);
        }
    }

    public function test_diff_reports_delta_of_executed_query(): void
    {
        DB::statement('DROP TABLE IF EXISTS stress_digest_probe');
        DB::statement('CREATE TABLE stress_digest_probe (id INT PRIMARY KEY, note VARCHAR(32))');

        try {
            // Baseline 1 panggilan sebelum snapshot: delta 1 harus terbaca 1, bukan 2.
            DB::table('stress_digest_probe')->insert(['id' => 0, 'note' => 'nol']);

            $this->artisan('stress:digests', ['--snapshot' => $this->snapshotPath])->assertSuccessful();

            DB::table('stress_digest_probe')->insert(['id' => 1, 'note' => 'satu']);

            $this->artisan('stress:digests', [
                '--diff' => $this->snapshotPath,
                '--out' => $this->diffPath,
                '--limit' => 3,
            ])->assertSuccessful();

            $data = json_decode((string) file_get_contents($this->diffPath), true);

            $this->assertIsArray($data);
            $this->assertArrayHasKey('generated_at', $data);
            $this->assertSame($this->snapshotPath, $data['before']);
            $this->assertGreaterThan(0, $data['totals']['calls']);
            $this->assertNotEmpty($data['deltas']);

            $probeDelta = $this->findDeltaFor($data['deltas'], 'stress_digest_probe');

            $this->assertNotNull($probeDelta, 'Delta untuk insert stress_digest_probe tidak ditemukan.');
            $this->assertSame(1, $probeDelta['calls']);
            $this->assertIsFloat($probeDelta['total_ms']);
            $this->assertGreaterThan(0, $probeDelta['total_ms']);
            $this->assertArrayHasKey('explain', $probeDelta);
            $this->assertArrayHasKey('explain_error', $probeDelta);

            DB::table('stress_digest_probe')->insert(['id' => 2, 'note' => 'dua']);

            $this->artisan('stress:digests', [
                '--diff' => $this->snapshotPath,
                '--out' => $this->diffPath,
                '--limit' => 3,
            ])->assertSuccessful();

            $second = json_decode((string) file_get_contents($this->diffPath), true);

            $this->assertIsArray($second);
            $secondProbeDelta = $this->findDeltaFor($second['deltas'], 'stress_digest_probe');

            $this->assertNotNull($secondProbeDelta, 'Delta kedua untuk insert stress_digest_probe tidak ditemukan.');
            $this->assertSame(2, $secondProbeDelta['calls']);

            $explained = array_filter(
                $second['deltas'],
                static fn (array $delta): bool => $delta['explain'] !== null || $delta['explain_error'] !== null,
            );

            $this->assertLessThanOrEqual(3, count($explained));

            foreach ($second['deltas'] as $delta) {
                $this->assertStringStartsNotWith('USE ', strtoupper($delta['digest_text']));
                $this->assertStringStartsNotWith('SET ', strtoupper($delta['digest_text']));
            }
        } finally {
            DB::statement('DROP TABLE IF EXISTS stress_digest_probe');
        }
    }

    public function test_limit_option_caps_explain_count(): void
    {
        $this->artisan('stress:digests', ['--snapshot' => $this->snapshotPath])->assertSuccessful();

        DB::table('users')->count();
        DB::select('select count(*) as c from forms');

        $this->artisan('stress:digests', [
            '--diff' => $this->snapshotPath,
            '--out' => $this->diffPath,
            '--limit' => 1,
        ])->assertSuccessful();

        $data = json_decode((string) file_get_contents($this->diffPath), true);

        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data['deltas']), 'Butuh minimal 2 delta agar --limit bermakna.');

        $explained = array_filter(
            $data['deltas'],
            static fn (array $delta): bool => $delta['explain'] !== null || $delta['explain_error'] !== null,
        );

        $this->assertSame(1, count($explained));
    }

    public function test_diff_explains_aggregate_query_with_normalised_digest_text(): void
    {
        $this->artisan('stress:digests', ['--snapshot' => $this->snapshotPath])->assertSuccessful();

        DB::table('users')->count();

        $this->artisan('stress:digests', [
            '--diff' => $this->snapshotPath,
            '--out' => $this->diffPath,
            '--limit' => 10,
        ])->assertSuccessful();

        $data = json_decode((string) file_get_contents($this->diffPath), true);
        $usersDelta = $this->findDeltaFor($data['deltas'], 'users');

        $this->assertNotNull($usersDelta);
        $this->assertNotNull($usersDelta['explain'], 'EXPLAIN agregat users harus berhasil: '.($usersDelta['explain_error'] ?? '-'));
        $this->assertNull($usersDelta['explain_error']);
        $this->assertIsArray($usersDelta['explain']);
        $this->assertArrayHasKey('query_block', $usersDelta['explain']);
    }

    public function test_command_rejects_missing_options(): void
    {
        $this->artisan('stress:digests')->assertFailed();
    }

    /**
     * Arahkan koneksi default ke MySQL container dan pastikan performance_schema terbaca.
     */
    private function useMysqlConnection(): void
    {
        $database = getenv('DB_DATABASE');

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            $this->markTestSkipped('DB_DATABASE MySQL tidak tersedia di environment.');
        }

        config([
            'database.connections.mysql.host' => $this->envValue('DB_HOST', '127.0.0.1'),
            'database.connections.mysql.port' => $this->envValue('DB_PORT', '3306'),
            'database.connections.mysql.database' => $database,
            'database.connections.mysql.username' => $this->envValue('DB_USERNAME', 'root'),
            'database.connections.mysql.password' => $this->envValue('DB_PASSWORD', ''),
            // MySQL 9.0 tidak mengisi DIGEST untuk native prepared statement
            // (`statement/com/Execute`, DIGEST NULL), jadi beban uji memakai
            // text protocol agar performance_schema mencatat digest-nya.
            'database.connections.mysql.options' => [PDO::ATTR_EMULATE_PREPARES => true],
        ]);

        try {
            DB::purge('mysql');
            DB::connection('mysql')->select('SELECT COUNT(*) AS total FROM performance_schema.events_statements_summary_by_digest');
        } catch (Throwable $exception) {
            $this->markTestSkipped('MySQL/performance_schema tidak bisa diakses: '.$exception->getMessage());
        }

        if (! Schema::connection('mysql')->hasTable('users')) {
            $this->markTestSkipped('Tabel users tidak ada di MySQL; jalankan migrasi dulu.');
        }

        DB::setDefaultConnection('mysql');
    }

    /**
     * Hapus artefak JSON; path kosong dilewati agar test yang di-skip tidak melempar Error.
     */
    private function deleteArtifactFiles(): void
    {
        $paths = array_values(array_filter(
            [$this->snapshotPath, $this->diffPath],
            static fn (string $path): bool => $path !== '',
        ));

        if ($paths === []) {
            return;
        }

        File::delete($paths);
    }

    /**
     * Baca nilai environment container dengan default eksplisit.
     */
    private function envValue(string $key, string $default): string
    {
        $value = getenv($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Cari delta pertama yang teks digest-nya memuat kata kunci tabel.
     *
     * @param  array<int, array<string, mixed>>  $deltas
     * @return array<string, mixed>|null
     */
    private function findDeltaFor(array $deltas, string $keyword): ?array
    {
        foreach ($deltas as $delta) {
            if (isset($delta['digest_text']) && is_string($delta['digest_text'])
                && Str::contains($delta['digest_text'], $keyword, ignoreCase: true)) {
                return $delta;
            }
        }

        return null;
    }
}

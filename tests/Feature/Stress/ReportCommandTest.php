<?php

namespace Tests\Feature\Stress;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Menguji `stress:report` merakit report.html self-contained dari satu direktori run.
 *
 * Fixture disalin ke direktori sementara agar report.html hasil render tidak
 * mencemari tests/Fixtures/stress/run.
 */
class ReportCommandTest extends TestCase
{
    private string $fixtureDir = '';

    private string $runDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = base_path('tests/Fixtures/stress/run');
        $this->runDir = storage_path('app/stress/report-command-test');

        File::deleteDirectory($this->runDir);
        File::copyDirectory($this->fixtureDir, $this->runDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->runDir);

        parent::tearDown();
    }

    public function test_report_contains_all_sections_and_inline_svg(): void
    {
        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        foreach ([
            'id="capacity"',
            'id="endpoints"',
            'id="queries"',
            'id="resources"',
            'id="volume"',
            'id="findings"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        $this->assertGreaterThanOrEqual(2, substr_count($html, '<svg'));
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('http://', $html);
        $this->assertStringContainsString('workers=4', $html);
        $this->assertStringContainsString('bypass', $html);
        $this->assertStringContainsString('generator_bound', $html);
        $this->assertStringContainsString('emulated_prepares', $html);
        $this->assertStringContainsString('Urut p95 tertinggi', $html);
        $this->assertStringContainsString('Urut total waktu terbesar', $html);
    }

    public function test_capacity_chart_uses_stream_ndjson_and_marks_break_point(): void
    {
        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertStringContainsString('titik patah: t=2s', $html);
        $this->assertStringContainsString('rps=20', $html);
        $this->assertStringContainsString('p95=2,500.00', $html);
        $this->assertStringContainsString('stroke-dasharray', $html);
        $this->assertStringContainsString('Kurva kapasitas per detik', $html);
    }

    public function test_report_without_stream_ndjson_falls_back_to_cpu_proxy(): void
    {
        File::delete($this->runDir.'/stream.ndjson');

        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertStringContainsString('absent', $html);
        $this->assertStringContainsString('tidak tersedia', $html);
        $this->assertStringContainsString('Proksi beban', $html);
        $this->assertStringNotContainsString('titik patah: t=', $html);
    }

    public function test_empty_stream_file_reports_empty_or_invalid(): void
    {
        File::put($this->runDir.'/stream.ndjson', '');

        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertStringContainsString('empty_or_invalid', $html);
        $this->assertStringContainsString('tidak berisi Point', $html);
        $this->assertStringContainsString('Proksi beban', $html);
    }

    public function test_429_series_is_rendered(): void
    {
        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertStringContainsString('429 %', $html);
        $this->assertStringContainsString('429=2.86%', $html);
        $this->assertStringContainsString('skala 10.000', $html);
    }

    public function test_status_zero_counts_as_failed_and_without_status(): void
    {
        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertStringContainsString('tanpa status=1 request', $html);
        $this->assertStringContainsString('gagal=1.43%', $html);
    }

    public function test_missed_second_is_emitted_as_zero_rps_bucket(): void
    {
        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertStringContainsString('detik kosong (rps 0)=1', $html);
    }

    public function test_point_without_endpoint_tag_does_not_add_rps(): void
    {
        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        // Fixture: 70 Point bertag endpoint + 1 Point login tanpa tag (tidak dihitung).
        $this->assertStringContainsString('total=70 request', $html);
    }

    public function test_cpu_line_breaks_when_sampler_gap_exceeds_tolerance(): void
    {
        File::put($this->runDir.'/resources.csv', implode("\n", [
            'ts,app_usage_usec,app_throttled_usec,app_mem_current,app_mem_peak,db_usage_usec,db_mem_current,db_mem_peak',
            '1792686553,576000000,0,726593536,792858624,134499119,688672768,833331200',
            '1792686554,577004279,0,725725184,792858624,134862873,688701440,833331200',
            // Gap 6 detik: bucket t=3 tidak punya sample dalam toleransi 2 detik.
            '1792686560,580291591,0,725323776,792858624,135320197,688173056,833331200',
            '1792686561,581171492,0,726052864,792858624,135556117,688705536,833331200',
        ])."\n");

        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertMatchesRegularExpression('/app CPU core[^)]*2 segmen/', $html);
    }

    public function test_explain_plan_is_rendered_readably(): void
    {
        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertStringContainsString('table=users', $html);
        $this->assertStringContainsString('access_type=eq_ref', $html);
        $this->assertStringContainsString('key=PRIMARY', $html);
        $this->assertStringContainsString('plan JSON', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('EXPLAIN gagal: placeholder', $html);
    }

    public function test_resource_statistics_ignore_forward_filled_samples(): void
    {
        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertSame(1, preg_match('/<th>app<\/th>\s*<td class="num">([0-9.]+)<\/td>/', $html, $matches));
        // Dua delta valid: 2.052.219 usec / 3 s dan 879.901 usec / 2 s → rata-rata 0,562 core.
        $this->assertSame('0.562', $matches[1]);
    }

    public function test_report_maps_sanitised_endpoint_back_to_original_target(): void
    {
        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertStringContainsString('/admin/dashboard', $html);
        $this->assertStringContainsString('dashboard.home', $html);
    }

    public function test_report_fails_when_required_artifacts_are_missing(): void
    {
        File::delete($this->runDir.'/summary.json');
        File::delete($this->runDir.'/resources.csv');

        $this->artisan('stress:report', ['run' => $this->runDir])
            ->expectsOutputToContain('summary.json, resources.csv')
            ->assertFailed();

        $this->assertFileDoesNotExist($this->runDir.'/report.html');
    }

    public function test_report_survives_empty_queries_and_header_only_csv(): void
    {
        File::put($this->runDir.'/queries.json', (string) json_encode([
            'generated_at' => '2026-10-22T16:00:31+00:00',
            'before' => 'digests-before.json',
            'totals' => ['calls' => 0, 'total_ms' => 0.0],
            'deltas' => [],
        ]));

        File::put(
            $this->runDir.'/resources.csv',
            "ts,app_usage_usec,app_throttled_usec,app_mem_current,app_mem_peak,db_usage_usec,db_mem_current,db_mem_peak\n",
        );

        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertStringContainsString('id="queries"', $html);
        $this->assertStringContainsString('id="resources"', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, '<svg'));
    }

    public function test_report_treats_empty_csv_cells_as_null_and_escapes_values(): void
    {
        File::put($this->runDir.'/targets.json', (string) json_encode([
            'targets' => [
                ['name' => 'home', 'method' => 'GET', 'url' => '/"><script>alert(1)</script>', 'writes' => false],
                ['name' => 'dashboard.home', 'method' => 'GET', 'url' => '/admin/dashboard', 'writes' => false],
            ],
        ]));

        $this->artisan('stress:report', ['run' => $this->runDir])->assertSuccessful();

        $html = $this->readReport();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * Baca report.html hasil render dari direktori run sementara.
     */
    private function readReport(): string
    {
        $path = $this->runDir.'/report.html';

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}

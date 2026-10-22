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

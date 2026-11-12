<?php

namespace Tests\Feature\Analytics;

use App\Models\FormAnswer;
use App\Services\Reporting\EventReportingQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perintah rebuild agregat harian harus mengisi bulan yang diharapkan dan idempoten
 * (dijalankan berulang menghasilkan angka yang sama).
 */
class RebuildDailyRegistrationsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_command_mengisi_tanggal_dan_bulan_yang_diharapkan(): void
    {
        FormAnswer::factory()->count(3)->create();
        FormAnswer::factory()->count(2)->create(['created_at' => now()->subMonthNoOverflow()]);

        $this->artisan('analytics:rebuild-daily-registrations')
            ->expectsOutputToContain('Agregat harian registrasi dihitung ulang: 5 registrasi pada 2 tanggal')
            ->assertSuccessful();

        $this->assertSame(2, DB::table('event_daily_registrations')->count());
        $this->assertSame(5, (int) DB::table('event_daily_registrations')->sum('count'));

        $trend = app(EventReportingQuery::class)->registrationTrendOverMonths(6);

        $this->assertSame(2, $trend[4]['count']);
        $this->assertSame(3, $trend[5]['count']);
    }

    public function test_command_idempoten_dan_menaikkan_buster_fallback(): void
    {
        FormAnswer::factory()->count(4)->create();

        $busterBefore = (int) Cache::get(EventReportingQuery::BUSTER_REGISTRATION_TREND_SOURCE, 0);

        $this->artisan('analytics:rebuild-daily-registrations')->assertSuccessful();
        $first = DB::table('event_daily_registrations')->orderBy('registration_date')->get()->toArray();

        $this->artisan('analytics:rebuild-daily-registrations')->assertSuccessful();
        $second = DB::table('event_daily_registrations')->orderBy('registration_date')->get()->toArray();

        $this->assertEquals($first, $second);
        $this->assertSame(4, (int) DB::table('event_daily_registrations')->sum('count'));
        $this->assertSame(
            $busterBefore + 2,
            (int) Cache::get(EventReportingQuery::BUSTER_REGISTRATION_TREND_SOURCE, 0)
        );
    }

    public function test_empty_source_mengosongkan_agregat(): void
    {
        DB::table('event_daily_registrations')->insert([
            'registration_date' => now()->toDateString(),
            'count' => 99,
        ]);

        $this->artisan('analytics:rebuild-daily-registrations')
            ->expectsOutputToContain('0 registrasi pada 0 tanggal')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('event_daily_registrations')->count());
    }
}

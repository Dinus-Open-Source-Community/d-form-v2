<?php

namespace Tests\Feature\Reporting;

use App\Models\FormAnswer;
use App\Services\Reporting\EventReportingQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tren registrasi harus dibaca dari agregat harian, bukan memindai form_answers.
 */
class RegistrationTrendTest extends TestCase
{
    use RefreshDatabase;

    public function test_tren_dibaca_dari_tabel_agregat_tanpa_menyentuh_form_answers(): void
    {
        DB::table('event_daily_registrations')->insert([
            ['registration_date' => now()->startOfMonth()->toDateString(), 'count' => 7],
            ['registration_date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'count' => 3],
        ]);

        DB::enableQueryLog();
        $trend = app(EventReportingQuery::class)->registrationTrendOverMonths(6);
        $sql = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
        DB::disableQueryLog();

        $this->assertStringContainsString('event_daily_registrations', $sql);
        $this->assertStringNotContainsString('form_answers', $sql);
        $this->assertSame(6, count($trend));
        $this->assertSame(7, $trend[5]['count']);
        $this->assertSame(3, $trend[4]['count']);
    }

    public function test_observer_menambah_dan_mengurangi_agregat_harian(): void
    {
        $answer = FormAnswer::factory()->create();
        $date = $answer->created_at?->toDateString();

        $this->assertDatabaseHas('event_daily_registrations', [
            'registration_date' => $date,
            'count' => 1,
        ]);

        $answer->delete();

        $this->assertDatabaseHas('event_daily_registrations', [
            'registration_date' => $date,
            'count' => 0,
        ]);
    }

    public function test_command_rebuild_menghitung_ulang_dari_sumber(): void
    {
        FormAnswer::factory()->count(3)->create();

        DB::table('event_daily_registrations')->update(['count' => 999]);

        $this->artisan('analytics:rebuild-daily-registrations')->assertSuccessful();

        $this->assertSame(3, (int) DB::table('event_daily_registrations')->sum('count'));
    }
}

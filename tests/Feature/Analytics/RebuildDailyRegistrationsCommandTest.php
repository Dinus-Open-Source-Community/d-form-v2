<?php

namespace Tests\Feature\Analytics;

use App\Models\FormAnswer;
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

    /**
     * Isi tabel agregat sebagai peta `registration_date => count` agar assertion
     * langsung menyatakan kontrak perintah: satu baris per tanggal submisi.
     *
     * @return array<string, int>
     */
    private function aggregateCounts(): array
    {
        return DB::table('event_daily_registrations')
            ->orderBy('registration_date')
            ->pluck('count', 'registration_date')
            ->map(static fn (int|string $count): int => (int) $count)
            ->all();
    }

    public function test_command_mengisi_tanggal_dan_bulan_yang_diharapkan(): void
    {
        FormAnswer::factory()->count(3)->create();
        FormAnswer::factory()->count(2)->create(['created_at' => now()->subMonthNoOverflow()]);

        $this->artisan('analytics:rebuild-daily-registrations')
            ->expectsOutputToContain('Agregat harian registrasi dihitung ulang: 5 registrasi pada 2 tanggal')
            ->assertSuccessful();

        $this->assertSame(
            [
                now()->subMonthNoOverflow()->toDateString() => 2,
                now()->toDateString() => 3,
            ],
            $this->aggregateCounts(),
        );
    }

    public function test_command_idempoten_dengan_isi_tabel_sama(): void
    {
        FormAnswer::factory()->count(4)->create();

        $this->artisan('analytics:rebuild-daily-registrations')->assertSuccessful();
        $first = DB::table('event_daily_registrations')->orderBy('registration_date')->get()->toArray();

        $this->artisan('analytics:rebuild-daily-registrations')->assertSuccessful();
        $second = DB::table('event_daily_registrations')->orderBy('registration_date')->get()->toArray();

        $this->assertEquals($first, $second);
        $this->assertSame([now()->toDateString() => 4], $this->aggregateCounts());
        $this->assertSame(4, (int) DB::table('event_daily_registrations')->sum('count'));
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

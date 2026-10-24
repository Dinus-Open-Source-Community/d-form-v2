<?php

namespace Tests\Feature\Dashboard;

use App\Models\Event;
use App\Services\Event\EventCache;
use App\Services\Registration\EventRegistrationCounter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Registrasi tidak boleh membuang cache statistik event (3 agregat 1 juta baris),
 * hanya cache jumlah pendaftar.
 */
class DashboardStatsCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    private function buster(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    public function test_reservasi_kursi_hanya_menginvalidasi_cache_pendaftar(): void
    {
        $event = Event::factory()->create(['registered_count' => 0, 'quota' => 100]);

        $statsBefore = $this->buster(EventCache::BUSTER_STATS);
        $registrantsBefore = $this->buster(EventCache::BUSTER_REGISTRANTS);

        app(EventRegistrationCounter::class)->reserveLocked($event, 1);

        $this->assertSame($statsBefore, $this->buster(EventCache::BUSTER_STATS));
        $this->assertSame($registrantsBefore + 1, $this->buster(EventCache::BUSTER_REGISTRANTS));
    }

    public function test_perubahan_atribut_event_menginvalidasi_statistik(): void
    {
        $event = Event::factory()->create();

        $statsBefore = $this->buster(EventCache::BUSTER_STATS);

        $event->update(['title' => 'Judul Berubah']);

        $this->assertSame($statsBefore + 1, $this->buster(EventCache::BUSTER_STATS));
    }

    public function test_jumlah_pendaftar_dashboard_memakai_cache_terpisah(): void
    {
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('admin');

        Event::factory()->count(2)->create(['registered_count' => 5, 'deleted_at' => null]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.totalRegistrants', 10));
    }
}

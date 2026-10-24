<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\Event\EventCache;
use App\Services\Reporting\EventReportingQuery;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cache dashboard diisi saat request pertama atau lewat `dashboard:warm` (alat opsional);
 * begitu terisi, request tidak lagi menjalankan agregat 1 juta baris.
 */
class DashboardCacheWarmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Cache::flush();

        // Chart tren memakai agregat harian; tanpa baris ini fallback `form_answers` ikut
        // berjalan dan mengaburkan assert "tidak ada query berat".
        DB::table('event_daily_registrations')->insert([
            'registration_date' => now()->toDateString(),
            'count' => 1,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Query yang dianggap berat: sumber `form_answers` atau agregat atas tabel `events`.
     *
     * @return list<string>
     */
    private function heavyQueries(): array
    {
        $heavy = [];

        foreach (DB::getQueryLog() as $entry) {
            $sql = strtolower((string) $entry['query']);

            $touchesFormAnswers = str_contains($sql, 'form_answers');
            $aggregatesEvents = preg_match('/from\s+[`"]?events[`"]?/', $sql) === 1
                && (str_contains($sql, 'group by') || str_contains($sql, 'count(*)'));

            if ($touchesFormAnswers || $aggregatesEvents) {
                $heavy[] = $sql;
            }
        }

        return $heavy;
    }

    public function test_command_mengisi_tiga_cache_dashboard(): void
    {
        Event::factory()->create([
            'category' => 'seminar,workshop',
            'status' => EventStatus::Published,
            'registered_count' => 7,
            'registration_end' => now()->addDays(5),
        ]);
        Event::factory()->create([
            'category' => 'seminar',
            'status' => EventStatus::Published,
            'registered_count' => 3,
            'registration_end' => now()->subDay(),
        ]);

        $exitCode = Artisan::call('dashboard:warm');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(EventReportingQuery::DASHBOARD_EVENT_STATS_KEY, $output);
        $this->assertStringContainsString(EventReportingQuery::DASHBOARD_REGISTRANTS_KEY, $output);
        $this->assertStringContainsString(EventReportingQuery::DASHBOARD_CATEGORY_TOKENS_KEY, $output);

        $this->assertSame(
            ['totalEvents' => 2, 'activeEvents' => 2, 'completionRate' => 50],
            EventCache::peek(EventReportingQuery::DASHBOARD_EVENT_STATS_KEY, EventCache::BUSTER_STATS),
        );
        $this->assertSame(
            ['total' => 10],
            EventCache::peek(EventReportingQuery::DASHBOARD_REGISTRANTS_KEY, EventCache::BUSTER_REGISTRANTS),
        );
        $this->assertSame(
            [
                ['token' => 'seminar', 'count' => 2],
                ['token' => 'workshop', 'count' => 1],
            ],
            EventCache::peek(EventReportingQuery::DASHBOARD_CATEGORY_TOKENS_KEY, EventCache::BUSTER_CATEGORY),
        );
    }

    public function test_request_setelah_warm_tidak_menjalankan_query_berat(): void
    {
        Event::factory()->create([
            'category' => 'seminar',
            'status' => EventStatus::Published,
            'registered_count' => 4,
            'registration_end' => now()->addDays(5),
        ]);

        Artisan::call('dashboard:warm');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.totalEvents', 1)
                ->where('stats.totalRegistrants', 4)
                ->where('adminCharts.categoryBreakdown.0.token', 'seminar')
                ->where('adminCharts.categoryBreakdown.0.count', 1));

        $heavy = $this->heavyQueries();
        DB::disableQueryLog();

        $this->assertSame([], $heavy);
    }

    public function test_request_dengan_cache_kosong_menghitung_saat_itu_juga(): void
    {
        Event::factory()->create([
            'category' => 'seminar',
            'status' => EventStatus::Published,
            'registered_count' => 9,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.totalEvents', 1)
                ->where('stats.activeEvents', 1)
                ->where('stats.totalRegistrants', 9)
                ->where('adminCharts.categoryBreakdown.0.token', 'seminar'));

        DB::disableQueryLog();

        // Cache terisi oleh request itu sendiri: panggilan berikutnya tidak menghitung ulang.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->reportingQuery()->dashboardEventStats();
        DB::disableQueryLog();

        $this->assertSame([], $this->heavyQueries());
    }

    private function reportingQuery(): EventReportingQuery
    {
        return app(EventReportingQuery::class);
    }

    public function test_buster_event_membuang_cache_sampai_warm_ulang(): void
    {
        Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_end' => now()->addDays(5),
        ]);

        Artisan::call('dashboard:warm');

        $query = app(EventReportingQuery::class);
        $this->assertSame(1, $query->dashboardEventStats()['totalEvents']);

        // Event baru menaikkan BUSTER_STATS: kunci lama tidak dipakai lagi, jadi angka
        // basi tidak muncul — request berikutnya menghitung ulang ke kunci baru.
        Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_end' => now()->addDays(5),
        ]);
        $this->assertSame(2, $query->dashboardEventStats()['totalEvents']);
    }

    public function test_warm_kedua_melewati_cache_terisi_dan_force_menghitung_ulang(): void
    {
        Event::factory()->create([
            'category' => 'seminar',
            'status' => EventStatus::Published,
            'registration_end' => now()->addDays(5),
        ]);

        Artisan::call('dashboard:warm');

        DB::flushQueryLog();
        DB::enableQueryLog();

        Artisan::call('dashboard:warm');
        $output = Artisan::output();

        $this->assertStringContainsString('dilewati, cache terisi', $output);
        $this->assertSame([], $this->heavyQueries());

        DB::flushQueryLog();

        Artisan::call('dashboard:warm', ['--force' => true]);

        $this->assertNotSame([], $this->heavyQueries());
        DB::disableQueryLog();
    }
}

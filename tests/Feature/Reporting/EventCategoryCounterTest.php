<?php

namespace Tests\Feature\Reporting;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\Event\EventCache;
use App\Services\Event\EventCategoryCounter;
use App\Services\Reporting\EventReportingQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Chart kategori dashboard dibaca dari tabel agregat `event_category_counts` yang
 * dijaga EventObserver, bukan dari `GROUP BY events.category`.
 */
class EventCategoryCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_menambah_token_kategori_tanpa_menyentuh_token_lain(): void
    {
        Event::factory()->create(['category' => 'seminar']);

        $this->assertSame(['seminar' => 1], $this->visibleTallies());

        Event::factory()->create(['category' => 'seminar,workshop']);

        $this->assertSame(['seminar' => 2, 'workshop' => 1], $this->visibleTallies());
    }

    public function test_update_kategori_memindahkan_hitungan_lama_ke_baru(): void
    {
        $event = Event::factory()->create(['category' => 'seminar,workshop']);

        $event->update(['category' => 'workshop,training']);

        $this->assertSame(['training' => 1, 'workshop' => 1], $this->visibleTallies());
    }

    public function test_soft_delete_menurunkan_dan_restore_menaikkan(): void
    {
        $event = Event::factory()->create(['category' => 'seminar,workshop']);

        $event->delete();

        $this->assertSame([], $this->visibleTallies());

        $event->restore();

        $this->assertSame(['seminar' => 1, 'workshop' => 1], $this->visibleTallies());
    }

    public function test_force_delete_setelah_soft_delete_tidak_mengurangi_dua_kali(): void
    {
        $event = Event::factory()->create(['category' => 'seminar']);
        $event->delete();

        $event->forceDelete();

        $this->assertSame([], $this->visibleTallies());
    }

    public function test_rebuild_menyamai_agregasi_langsung_untuk_data_campuran(): void
    {
        Event::factory()->create(['category' => 'seminar,workshop']);
        Event::factory()->create(['category' => ' seminar , workshop,seminar ']);
        Event::factory()->create(['category' => '']);
        Event::factory()->create(['category' => '   ,  ']);
        Event::factory()->create(['category' => 'seminar']);
        Event::factory()->create(['category' => 'workshop'])->delete();

        DB::table(EventCategoryCounter::TABLE)->delete();
        DB::table(EventCategoryCounter::TABLE)->insert(['category' => 'stale', 'total' => 99]);

        $result = app(EventCategoryCounter::class)->rebuild();

        $this->assertSame(['seminar' => 3, 'workshop' => 2], $this->visibleTallies());
        $this->assertSame(['events' => 5, 'tokens' => 2], $result);
        $this->assertSame(
            [['token' => 'seminar', 'count' => 3], ['token' => 'workshop', 'count' => 2]],
            $this->sortedCounts(),
        );
    }

    public function test_update_kolom_lain_tidak_menulis_tabel_agregat(): void
    {
        $event = Event::factory()->create(['category' => 'seminar']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $event->update(['title' => 'Judul Baru Tanpa Ubah Kategori']);

        $writes = array_filter(
            DB::getQueryLog(),
            static fn (array $entry): bool => str_contains((string) $entry['query'], EventCategoryCounter::TABLE),
        );
        DB::disableQueryLog();

        $this->assertSame([], $writes);
        $this->assertSame(['seminar' => 1], $this->visibleTallies());
    }

    public function test_chart_dashboard_membaca_tabel_agregat_bukan_group_by_events(): void
    {
        Event::factory()->create([
            'category' => 'seminar,workshop',
            'status' => EventStatus::Published,
            'registration_end' => now()->addDays(5),
        ]);
        Event::factory()->create([
            'category' => 'seminar',
            'status' => EventStatus::Published,
            'registration_end' => now()->addDays(5),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        Artisan::call('dashboard:warm', ['--force' => true]);

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $groupByEvents = array_filter(
            $queries,
            static fn (string $sql): bool => str_contains(strtolower($sql), 'from "events"')
                && str_contains(strtolower($sql), 'group by'),
        );

        $this->assertSame([], $groupByEvents);
        $this->assertNotEmpty(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, EventCategoryCounter::TABLE),
        ));

        $this->assertSame(
            [['token' => 'seminar', 'count' => 2], ['token' => 'workshop', 'count' => 1]],
            EventCache::peek(EventReportingQuery::DASHBOARD_CATEGORY_TOKENS_KEY, EventCache::BUSTER_CATEGORY),
        );
    }

    /**
     * Baris agregat yang masih tampil di chart (total 0 disembunyikan, sama seperti `counts()`).
     *
     * @return array<string, int>
     */
    private function visibleTallies(): array
    {
        return DB::table(EventCategoryCounter::TABLE)
            ->where('total', '>', 0)
            ->orderBy('category')
            ->pluck('total', 'category')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Keluaran service apa adanya (urut total desc) untuk memeriksa bentuk kontrak chart.
     *
     * @return list<array{token: string, count: int}>
     */
    private function sortedCounts(): array
    {
        return app(EventCategoryCounter::class)->counts();
    }
}

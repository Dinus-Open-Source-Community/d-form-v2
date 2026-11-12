<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Event;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Jalur pencarian MySQL (FULLTEXT). Dijalankan hanya bila driver = mysql:
 * `php artisan test --group=mysql` dengan database uji terpisah.
 *
 * @group mysql
 */
class EventListSearchMysqlTest extends TestCase
{
    // Transaksi (RefreshDatabase) membuat FULLTEXT tak melihat baris yang belum commit.
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Pencarian FULLTEXT hanya diuji pada MySQL.');
        }

        Cache::flush();
    }

    /** Request Inertia dengan versi aset yang cocok agar respons berisi props JSON. */
    private function inertiaGet(string $uri): TestResponse
    {
        return $this->get($uri, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        ]);
    }

    private function publishedEvent(string $title): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::Published,
            'title' => $title,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
    }

    public function test_prefix_search_matches_title(): void
    {
        $this->publishedEvent('Seminar Laravel Dasar');
        $this->publishedEvent('Workshop Data Science');

        $this->inertiaGet('/events?q=seminar')
            ->assertOk()
            ->assertJsonCount(1, 'props.events')
            ->assertJsonPath('props.events.0.title', 'Seminar Laravel Dasar')
            ->assertJsonPath('props.searchError', null);
    }

    public function test_stopwords_are_dropped_and_remaining_tokens_match(): void
    {
        $this->publishedEvent('Seminar Jakarta 2026');
        $this->publishedEvent('Workshop Bandung');

        // 'di' stopword dibuang; '+seminar* +jakarta*' berarti kedua token wajib cocok.
        $this->inertiaGet('/events?q='.urlencode('seminar di jakarta'))
            ->assertOk()
            ->assertJsonCount(1, 'props.events')
            ->assertJsonPath('props.events.0.title', 'Seminar Jakarta 2026');
    }

    public function test_boolean_operator_input_does_not_error(): void
    {
        $this->publishedEvent('Seminar Laravel Dasar');

        $this->inertiaGet('/events?q='.urlencode('+-~*()<>"'))
            ->assertOk()
            ->assertJsonPath('props.searchError', null);
    }

    public function test_broad_keyword_is_cut_off_and_reported_without_server_error(): void
    {
        $this->seedStressEvents(50000);

        $this->inertiaGet('/events?q=stress')
            ->assertOk()
            ->assertJsonCount(0, 'props.events')
            ->assertJsonPath('props.pagination.links', [])
            ->assertJsonPath('props.searchError', 'Kata kunci terlalu umum, coba lebih spesifik.');
    }

    public function test_too_broad_marker_skips_the_heavy_query_on_repeat(): void
    {
        $this->seedStressEvents(50000);

        $this->inertiaGet('/events?q=stress')
            ->assertOk()
            ->assertJsonPath('props.searchError', 'Kata kunci terlalu umum, coba lebih spesifik.');

        DB::enableQueryLog();
        $this->inertiaGet('/events?q=STRESS')
            ->assertOk()
            ->assertJsonPath('props.searchError', 'Kata kunci terlalu umum, coba lebih spesifik.');
        $matchQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'match(title, location)'));
        DB::disableQueryLog();

        // Huruf besar/kecil berbagi key cache yang sama: tidak ada query FULLTEXT kedua.
        $this->assertCount(0, $matchQueries);
    }

    /** Isi tabel dengan kata umum agar batas 1 ms pasti terlampaui. */
    private function seedStressEvents(int $total): void
    {
        config(['events.search.max_execution_ms' => 1]);

        $timestamp = now();
        $rows = [];

        for ($index = 1; $index <= $total; $index++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'title' => 'Stress Event '.$index,
                'slug' => 'stress-event-'.$index,
                'description' => 'Dataset stress event '.$index.'.',
                'start_date' => '2026-12-31',
                'end_date' => '2026-12-31',
                'registration_start' => '2026-01-01 00:00:00',
                'registration_end' => '2026-12-30 00:00:00',
                'location' => 'Stress Hall',
                'quota' => 100,
                'registered_count' => 0,
                'banner' => '',
                'price' => 0,
                'session' => 'general',
                'status' => EventStatus::Published->value,
                'category' => 'rkt',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('events')->insert($chunk);
        }
    }
}

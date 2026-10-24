<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Services\Event\PublicEventSearch;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Halaman publik `/events`: 10 acara per halaman dan pencarian diproses di server.
 */
class EventListPaginationTest extends TestCase
{
    use RefreshDatabase;

    /** Request Inertia dengan versi aset yang cocok agar respons berisi props JSON. */
    private function inertiaGet(string $uri): TestResponse
    {
        return $this->get($uri, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        ]);
    }

    private function publishedEvent(int $dayOffset, string $title = ''): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::Published,
            'start_date' => now()->addDays($dayOffset)->toDateString(),
            'end_date' => now()->addDays($dayOffset + 1)->toDateString(),
            'title' => $title !== '' ? $title : 'Acara '.$dayOffset,
        ]);
    }

    public function test_list_uses_cursor_pagination_ordered_by_newest_start_date(): void
    {
        foreach (range(1, 12) as $offset) {
            $this->publishedEvent($offset);
        }

        DB::enableQueryLog();

        $first = $this->inertiaGet('/events')
            ->assertOk()
            ->assertJsonCount(10, 'props.events')
            ->assertJsonPath('props.pagination.hasMore', true)
            ->assertJsonPath('props.pagination.currentPage', null)
            ->assertJsonPath('props.events.0.title', 'Acara 12')
            ->assertJsonCount(3, 'props.highlight');

        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('offset', strtolower($query['query']));
        }

        DB::disableQueryLog();

        $nextUrl = collect($first->json('props.pagination.links'))->firstWhere('label', 'Berikutnya')['url'];
        $this->assertIsString($nextUrl);
        $this->assertStringContainsString('cursor=', $nextUrl);

        $this->inertiaGet(parse_url($nextUrl, PHP_URL_PATH).'?'.parse_url($nextUrl, PHP_URL_QUERY))
            ->assertOk()
            ->assertJsonCount(2, 'props.events')
            ->assertJsonPath('props.pagination.hasMore', false)
            ->assertJsonPath('props.events.0.title', 'Acara 2');
    }

    public function test_search_filters_events_on_the_server(): void
    {
        $this->publishedEvent(1, 'Workshop Laravel Dasar');
        $this->publishedEvent(2, 'Seminar Data Science');

        $this->inertiaGet('/events?q=Laravel')
            ->assertOk()
            ->assertJsonCount(1, 'props.events')
            ->assertJsonPath('props.events.0.title', 'Workshop Laravel Dasar')
            ->assertJsonPath('props.filters.q', 'Laravel');
    }

    public function test_search_shorter_than_three_characters_is_ignored(): void
    {
        $this->publishedEvent(1, 'Workshop Laravel');
        $this->publishedEvent(2, 'Seminar Data');

        $this->inertiaGet('/events?q=La')
            ->assertOk()
            ->assertJsonCount(2, 'props.events');
    }

    public function test_case_variants_share_cache_but_each_response_keeps_its_own_query_link(): void
    {
        foreach (range(1, 12) as $offset) {
            $this->publishedEvent($offset, 'Seminar '.$offset);
        }

        $first = $this->inertiaGet('/events?q=Seminar')->assertOk();
        $search = app(PublicEventSearch::class);
        $this->assertTrue(Cache::has($search->cacheKey((string) PublicEventSearch::terms('Seminar'), 1)));

        DB::enableQueryLog();
        $second = $this->inertiaGet('/events?q=seminar')->assertOk();
        $eventQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'from "events"'));
        DB::disableQueryLog();

        // Hanya query highlight; daftar diambil dari cache.
        $this->assertCount(1, $eventQueries);

        $firstNext = collect($first->json('props.pagination.links'))->firstWhere('label', 'Berikutnya')['url'];
        $secondNext = collect($second->json('props.pagination.links'))->firstWhere('label', 'Berikutnya')['url'];

        $this->assertIsString($firstNext);
        $this->assertIsString($secondNext);
        $this->assertStringContainsString('q=Seminar', $firstNext);
        $this->assertStringContainsString('q=seminar', $secondNext);
        $this->assertStringNotContainsString('q=Seminar', $secondNext);
    }

    public function test_busy_search_lock_returns_friendly_error_without_querying_events(): void
    {
        foreach (range(1, 12) as $offset) {
            $this->publishedEvent($offset, 'Seminar '.$offset);
        }

        config(['events.search.lock_wait_seconds' => 0]);

        $search = app(PublicEventSearch::class);
        $terms = (string) PublicEventSearch::terms('Seminar');
        $lock = Cache::lock($search->lockKey($terms, 1), 10);
        $this->assertTrue($lock->get());

        DB::enableQueryLog();
        $this->inertiaGet('/events?q=Seminar')
            ->assertOk()
            ->assertJsonPath('props.searchError', 'Kata kunci terlalu umum, coba lebih spesifik.');
        $eventQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'from "events"'));
        DB::disableQueryLog();

        $lock->release();

        // Highlight tetap dirender; daftar tidak di-query karena lock dipegang request lain.
        $this->assertCount(1, $eventQueries);
    }

    public function test_search_pages_beyond_the_limit_redirect_to_first_page(): void
    {
        $this->publishedEvent(1, 'Seminar Laravel');

        $this->inertiaGet('/events?q=seminar&page=21')
            ->assertRedirect('/events?q=seminar');

        $this->inertiaGet('/events?q=seminar&page=20')
            ->assertOk();
    }

    public function test_list_excludes_draft_and_trashed_events(): void
    {
        $this->publishedEvent(1);
        Event::factory()->create([
            'status' => EventStatus::Draft,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'title' => 'Draft Acara',
        ]);
        $trashed = $this->publishedEvent(3);
        $trashed->delete();

        $this->inertiaGet('/events')
            ->assertOk()
            ->assertJsonCount(1, 'props.events')
            ->assertJsonCount(1, 'props.highlight');
    }
}

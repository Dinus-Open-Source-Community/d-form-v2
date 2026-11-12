<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Halaman /browse tidak boleh menarik semua event: 5 per halaman, urut tanggal
 * mulai terbaru, filter kategori/sesi diproses di server.
 */
class BrowsePaginationTest extends TestCase
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

    private function publishedEvent(int $dayOffset, array $attributes = []): Event
    {
        return Event::factory()->create(array_merge([
            'status' => EventStatus::Published,
            'start_date' => now()->addDays($dayOffset)->toDateString(),
            'end_date' => now()->addDays($dayOffset + 1)->toDateString(),
        ], $attributes));
    }

    public function test_browse_returns_five_events_per_page_ordered_by_newest_start_date(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 7) as $offset) {
            $this->publishedEvent($offset);
        }

        $this->actingAs($user);
        $this->inertiaGet('/browse?page=1')
            ->assertOk()
            ->assertJsonPath('props.pagination.currentPage', null)
            ->assertJsonPath('props.pagination.hasMore', true)
            ->assertJsonCount(5, 'props.events')
            ->assertJsonPath('props.events.0.start_date', now()->addDays(7)->toDateString());

        $this->actingAs($user);
        $nextUrl = collect($this->inertiaGet('/browse')->json('props.pagination.links'))
            ->firstWhere('label', 'Berikutnya')['url'];
        $this->assertIsString($nextUrl);

        $this->inertiaGet(parse_url($nextUrl, PHP_URL_PATH).'?'.parse_url($nextUrl, PHP_URL_QUERY))
            ->assertOk()
            ->assertJsonPath('props.pagination.hasMore', false)
            ->assertJsonCount(2, 'props.events');
    }

    public function test_browse_filters_category_on_the_server(): void
    {
        $user = User::factory()->create();
        $this->publishedEvent(1, ['category' => 'rkt']);
        $this->publishedEvent(2, ['category' => 'non-rkt']);

        $this->actingAs($user);
        $this->inertiaGet('/browse?category=rkt')
            ->assertOk()
            ->assertJsonCount(1, 'props.events')
            ->assertJsonPath('props.events.0.category.0', 'rkt');
    }

    public function test_browse_rejects_unknown_filter_values(): void
    {
        $this->actingAs(User::factory()->create());
        $this->inertiaGet('/browse?category=bukan-kategori')
            ->assertSessionHasErrors('category');
    }

    public function test_browse_ignores_draft_and_trashed_events(): void
    {
        $user = User::factory()->create();
        $this->publishedEvent(1);
        $this->publishedEvent(2, ['status' => EventStatus::Draft]);
        $this->publishedEvent(3, ['deleted_at' => now()]);

        $this->actingAs($user);
        $this->inertiaGet('/browse')
            ->assertOk()
            ->assertJsonCount(1, 'props.events');
    }
}

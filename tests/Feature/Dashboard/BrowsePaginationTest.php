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
 * Halaman /browse memakai paginasi bernomor seperti daftar admin: 8 per halaman,
 * urut acara yang paling baru dibuat, filter kategori/sesi diproses di server.
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

    public function test_browse_paginates_with_page_numbers_and_orders_by_newest_created(): void
    {
        $user = User::factory()->create();

        // Dibuat dari tanggal mulai paling akhir ke paling awal, sehingga urutan
        // tanggal mulai tidak bisa dipakai untuk menebak urutan yang benar.
        $created = [];
        foreach (range(10, 1) as $offset) {
            $created[] = $this->publishedEvent($offset);
        }

        $this->actingAs($user);

        $first = $this->inertiaGet('/browse?page=1')->assertOk();
        $first->assertJsonPath('props.pagination.currentPage', 1)
            ->assertJsonPath('props.pagination.lastPage', 2)
            ->assertJsonPath('props.pagination.total', 10)
            ->assertJsonPath('props.pagination.perPage', 8)
            ->assertJsonCount(8, 'props.events')
            ->assertJsonPath('props.events.0.id', $created[9]->id)
            ->assertJsonPath('props.events.7.id', $created[2]->id);

        $this->actingAs($user);
        $this->inertiaGet('/browse?page=2')
            ->assertOk()
            ->assertJsonPath('props.pagination.currentPage', 2)
            ->assertJsonCount(2, 'props.events')
            ->assertJsonPath('props.events.0.id', $created[1]->id)
            ->assertJsonPath('props.events.1.id', $created[0]->id);
    }

    public function test_browse_honours_per_page(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 10) as $offset) {
            $this->publishedEvent($offset);
        }

        $this->actingAs($user);
        $this->inertiaGet('/browse?per_page=3&page=2')
            ->assertOk()
            ->assertJsonPath('props.pagination.perPage', 3)
            ->assertJsonPath('props.pagination.lastPage', 4)
            ->assertJsonPath('props.pagination.currentPage', 2)
            ->assertJsonCount(3, 'props.events');
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

<?php

namespace Tests\Feature\Dashboard;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Daftar event admin: default urut acara yang paling baru dibuat, sort eksplisit tetap dihormati. */
class EventListOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function inertiaGet(string $uri): TestResponse
    {
        return $this->get($uri, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_default_orders_by_newest_created_first(): void
    {
        $admin = $this->admin();

        $older = Event::factory()->create(['created_by' => $admin->id, 'title' => 'A older']);
        $newer = Event::factory()->create(['created_by' => $admin->id, 'title' => 'Z newer']);

        $older->forceFill(['created_at' => now()->subDay()])->save();

        $this->actingAs($admin);
        $response = $this->inertiaGet('/admin/events')->assertOk();

        $this->assertSame($newer->id, $response->json('props.events.data.0.id'));
        $this->assertSame($older->id, $response->json('props.events.data.1.id'));
    }

    public function test_explicit_sort_is_still_honoured(): void
    {
        $admin = $this->admin();

        $older = Event::factory()->create(['created_by' => $admin->id, 'title' => 'A older']);
        Event::factory()->create(['created_by' => $admin->id, 'title' => 'Z newer']);

        $older->forceFill(['created_at' => now()->subDay()])->save();

        $this->actingAs($admin);
        $response = $this->inertiaGet('/admin/events?sort[by]=title&sort[order]=asc')->assertOk();

        $this->assertSame('A older', $response->json('props.events.data.0.title'));
    }
}

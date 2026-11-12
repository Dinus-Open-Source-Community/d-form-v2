<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Daftar admin: filter tidak boleh bocor ke event created_by NULL, dan halaman dibatasi.
 */
class EventListFiltersTest extends TestCase
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

    public function test_filter_status_tidak_bocor_ke_event_tanpa_pemilik(): void
    {
        Event::factory()->create([
            'status' => EventStatus::Draft,
            'created_by' => null,
            'title' => 'Draft Tanpa Pemilik',
        ]);
        Event::factory()->create([
            'status' => EventStatus::Published,
            'created_by' => null,
            'title' => 'Published Tanpa Pemilik',
        ]);

        $response = $this->actingAs($this->admin())
            ->inertiaGet('/admin/events?filter%5Bstatuses%5D%5B%5D=draft')
            ->assertOk();

        $titles = collect($response->json('props.events.data'))->pluck('title')->all();

        $this->assertContains('Draft Tanpa Pemilik', $titles);
        $this->assertNotContains('Published Tanpa Pemilik', $titles);
    }

    public function test_halaman_daftar_dibatasi(): void
    {
        $this->actingAs($this->admin());

        $response = $this->inertiaGet('/admin/events?page=99999')->assertOk();

        $this->assertSame(200, (int) $response->json('props.events.current_page'));
    }
}

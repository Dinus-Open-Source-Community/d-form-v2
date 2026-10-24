<?php

namespace Tests\Feature\Dashboard;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Daftar event admin: default 8 per halaman dan per_page dari UI dihormati.
 */
class EventListPerPageTest extends TestCase
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

    public function test_default_delapan_baris_per_halaman(): void
    {
        Event::factory()->count(12)->create(['created_by' => null]);

        $this->actingAs($this->admin());

        $response = $this->inertiaGet('/admin/events')->assertOk();

        $this->assertCount(8, $response->json('props.events.data'));
        $this->assertSame(8, (int) $response->json('props.events.per_page'));
    }

    public function test_per_page_dari_ui_dihormati(): void
    {
        Event::factory()->count(30)->create(['created_by' => null]);

        $this->actingAs($this->admin());

        $response = $this->inertiaGet('/admin/events?per_page=25')->assertOk();

        $this->assertCount(25, $response->json('props.events.data'));
        $this->assertSame(25, (int) $response->json('props.events.per_page'));
    }

    public function test_per_page_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->admin());

        $this->inertiaGet('/admin/events?per_page=0')->assertStatus(302);
    }
}

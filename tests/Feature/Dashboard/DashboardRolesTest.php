<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_dashboard_returns_stats_and_recent_events(): void
    {
        Event::factory()->count(2)->create(['status' => EventStatus::Published]);
        $this->artisan('dashboard:warm');

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->where('stats.totalEvents', 2)
                ->where('stats.activeEvents', 2)
                ->has('recentEvents', 2));
    }

    public function test_admin_stats_refresh_after_event_changes(): void
    {
        $user = $this->userWithRole('admin');
        Event::factory()->create(['status' => EventStatus::Published]);
        $this->artisan('dashboard:warm');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('stats.totalEvents', 1));

        Event::factory()->create(['status' => EventStatus::Published]);
        $this->artisan('dashboard:warm');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('stats.totalEvents', 2));
    }

    public function test_member_cannot_reach_the_organizer_dashboard(): void
    {
        $this->actingAs($this->userWithRole('member'))
            ->get(route('dashboard'))
            ->assertNotFound();
    }
}

<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
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

    public function test_admin_dashboard_returns_stats_charts_and_recent_events(): void
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
                ->has('recentEvents', 2)
                ->has('adminCharts.registrationTrend')
                ->has('adminCharts.categoryBreakdown'));
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

    public function test_member_dashboard_builds_stats_from_own_submissions(): void
    {
        $member = $this->userWithRole('member');

        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
        ]);
        $form = Form::factory()->create(['event_id' => $event->id]);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $member->id]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/User/Index')
                ->where('stats.eventsJoined', 1)
                ->where('stats.upcomingEvents', 1)
                ->has('upcomingEvents', 1)
                ->has('calendarEvents', 1));
    }

    public function test_member_dashboard_ignores_events_without_submission(): void
    {
        Event::factory()->create(['status' => EventStatus::Published]);

        $this->actingAs($this->userWithRole('member'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/User/Index')
                ->where('stats.eventsJoined', 0)
                ->has('calendarEvents', 0));
    }
}

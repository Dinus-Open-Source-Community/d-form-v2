<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\FormAnswer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class DashboardWeeklyKpiTrendsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-11-13 12:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_dashboard_exposes_six_week_buckets_with_boundaries_and_cohort_rate(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->createEventAt('2026-10-05 00:01:00', EventStatus::Published, 200, 50);
        $this->createEventAt('2026-11-03 12:00:00', EventStatus::Published, 200, 50);
        $this->createEventAt('2026-11-10 12:00:00', EventStatus::Published, 100, 25);
        $this->createEventAt('2026-11-12 12:00:00', EventStatus::Draft, 100, 10);

        $deleted = $this->createEventAt('2026-11-11 12:00:00', EventStatus::Published, 100, 100);
        $deleted->delete();

        DB::table('event_daily_registrations')->insert([
            ['registration_date' => '2026-10-04', 'count' => 50],
            ['registration_date' => '2026-11-03', 'count' => 8],
            ['registration_date' => '2026-11-09', 'count' => 4],
            ['registration_date' => '2026-11-15', 'count' => 6],
            ['registration_date' => '2026-11-16', 'count' => 99],
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->has('weeklyKpiTrends.totalEvents', 6)
                ->has('weeklyKpiTrends.activeEvents', 6)
                ->has('weeklyKpiTrends.totalRegistrants', 6)
                ->has('weeklyKpiTrends.completionRate', 6)
                ->where('weeklyKpiTrends.totalEvents.4.value', 1)
                ->where('weeklyKpiTrends.totalEvents.5.value', 2)
                ->where('weeklyKpiTrends.activeEvents.5.value', 1)
                ->where('weeklyKpiTrends.totalRegistrants.4.value', 8)
                ->where('weeklyKpiTrends.totalRegistrants.5.value', 10)
                ->where('weeklyKpiTrends.completionRate.4.value', 25)
                ->where('weeklyKpiTrends.completionRate.5.value', 25));
    }

    public function test_weekly_registration_falls_back_when_aggregate_has_no_recent_rows(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        DB::table('event_daily_registrations')->insert([
            ['registration_date' => '2026-09-01', 'count' => 100],
        ]);

        FormAnswer::withoutEvents(fn (): array => FormAnswer::factory()
            ->count(2)
            ->create(['created_at' => Carbon::parse('2026-11-10 12:00:00')])
            ->all());

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->where('weeklyKpiTrends.totalRegistrants.5.value', 2));
    }

    private function createEventAt(string $createdAt, EventStatus $status, int $quota, int $registeredCount): Event
    {
        return Event::factory()->create([
            'created_at' => Carbon::parse($createdAt, 'Asia/Jakarta'),
            'status' => $status,
            'quota' => $quota,
            'registered_count' => $registeredCount,
        ]);
    }
}

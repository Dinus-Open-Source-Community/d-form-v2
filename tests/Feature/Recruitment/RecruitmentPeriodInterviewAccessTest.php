<?php

namespace Tests\Feature\Recruitment;

use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentPeriodInterviewAccessTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->period = RecruitmentPeriod::factory()->create();
    }

    public function test_staff_dan_interviewer_memegang_permission_periods_view(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        $this->assertTrue($staff->can('recruitment.periods.view'));
        $this->assertTrue($interviewer->can('recruitment.periods.view'));
    }

    public function test_staff_dapat_membuka_tab_interview(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $this->actingAs($staff)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'interview']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tab', 'interview'));
    }

    public function test_interviewer_dapat_membuka_tab_interview(): void
    {
        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        $this->actingAs($interviewer)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'interview']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tab', 'interview'));
    }

    public function test_user_tanpa_hak_schedule_dan_queue_ditolak(): void
    {
        $outsider = User::factory()->create();
        // Middleware grup `recruitment.access` butuh dashboard.view; tanpa itu user
        // dialihkan ke dashboard (302) dan gate tab tidak pernah diuji.
        $outsider->givePermissionTo(['recruitment.dashboard.view', 'recruitment.periods.view']);

        $this->actingAs($outsider)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'interview']))
            ->assertForbidden();
    }
}

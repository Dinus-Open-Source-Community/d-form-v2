<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\RecruitmentPeriodStatus;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
    }

    public function test_guest_is_redirected_from_recruitment_dashboard(): void
    {
        $this->get(route('dashboard.recruitment.index'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_member_cannot_access_recruitment_dashboard(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)
            ->get(route('dashboard.recruitment.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_access_recruitment_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard/Recruitment/Index'));
    }

    public function test_admin_can_create_and_open_recruitment_period(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.periods.store'), [
                'name' => 'OpRec 2026',
                'description' => 'Test period',
                'registration_opens_at' => now()->addDay()->toDateTimeString(),
                'registration_closes_at' => now()->addMonth()->toDateTimeString(),
            ])
            ->assertRedirect();

        $period = RecruitmentPeriod::query()->where('name', 'OpRec 2026')->first();
        $this->assertNotNull($period);
        $this->assertSame(RecruitmentPeriodStatus::Draft, $period->status);

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.periods.open', $period))
            ->assertRedirect();

        $period->refresh();
        $this->assertSame(RecruitmentPeriodStatus::Open, $period->status);
    }

    public function test_recruitment_division_seeder_creates_four_divisions(): void
    {
        $this->assertSame(4, RecruitmentDivision::query()->count());
        $this->assertTrue(RecruitmentDivision::query()->where('code', 'programming')->exists());
    }

    public function test_admin_can_assign_interviewer_to_division(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.interviewers.assign'), [
                'user_id' => $interviewer->id,
                'recruitment_division_id' => $division->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_interviewer_divisions', [
            'user_id' => $interviewer->id,
            'recruitment_division_id' => $division->id,
        ]);
    }

    public function test_recruitment_staff_can_access_dashboard_but_not_periods_crud(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $this->actingAs($staff)
            ->get(route('dashboard.recruitment.index'))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('dashboard.recruitment.periods.index'))
            ->assertForbidden();
    }
}

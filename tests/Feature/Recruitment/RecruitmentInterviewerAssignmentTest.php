<?php

namespace Tests\Feature\Recruitment;

use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentInterviewerAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private RecruitmentInterviewerDivision $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo('recruitment.interviewers.assign');
        $this->admin->givePermissionTo('recruitment.periods.view');

        $division = RecruitmentDivision::query()->firstOrFail();
        $interviewer = User::factory()->create();

        $this->assignment = RecruitmentInterviewerDivision::query()->create([
            'user_id' => $interviewer->id,
            'recruitment_division_id' => $division->id,
        ]);
    }

    public function test_unassign_menghapus_penugasan_interviewer(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('dashboard.recruitment.interviewers.unassign', $this->assignment))
            ->assertRedirect();

        $this->assertDatabaseMissing('recruitment_interviewer_divisions', [
            'id' => $this->assignment->id,
        ]);
    }

    public function test_tab_interviewer_tetap_ok_setelah_unassign(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('dashboard.recruitment.interviewers.unassign', $this->assignment))
            ->assertRedirect();

        $period = RecruitmentPeriod::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $period->id,
                'tab' => 'interviewer',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('assignments', 0));
    }

    public function test_unassign_memunculkan_toast_sukses(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('dashboard.recruitment.interviewers.unassign', $this->assignment))
            ->assertSessionHas('inertia.flash_data', fn ($flash) => ($flash['toast']['message'] ?? null) === 'Penugasan interviewer dihapus.'
                && ($flash['toast']['type'] ?? null) === 'success');
    }
}

<?php

namespace Tests\Feature\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentApplicantPanelTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private RecruitmentPeriod $otherPeriod;

    private RecruitmentApplication $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $this->period = RecruitmentPeriod::factory()->create();
        $this->otherPeriod = RecruitmentPeriod::factory()->create();

        $this->application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'full_name' => 'Budi Santoso',
        ]);
    }

    private function admin(array $permissions = []): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        foreach ($permissions as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    public function test_application_param_memuat_detail_ter_scoped(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'application' => $this->application->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applicant_detail.id', $this->application->id)
                ->where('applicant_detail.full_name', 'Budi Santoso'));
    }

    public function test_application_param_lintas_periode_ditolak_404(): void
    {
        $foreign = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->otherPeriod->id,
        ]);

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'application' => $foreign->id,
            ]))
            ->assertNotFound();
    }

    public function test_application_param_tidak_ditemukan_404(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'application' => '11111111-1111-4111-8111-111111111111',
            ]))
            ->assertNotFound();
    }

    public function test_application_param_bukan_uuid_diabaikan_tanpa_error(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'application' => 'bukan-uuid',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('applicant_detail'));
    }

    public function test_tanpa_application_param_tidak_ada_payload_detail(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->missing('applicant_detail'));
    }

    public function test_tanpa_permission_list_tidak_ada_payload_detail(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('recruitment.dashboard.view');
        $viewer->givePermissionTo('recruitment.periods.view');

        $this->actingAs($viewer)
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'application' => $this->application->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('applicant_detail'));
    }
}

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

class RecruitmentPeriodQueryTabsTest extends TestCase
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

    private function admin(array $permissions = []): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        foreach ($permissions as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    public function test_tab_param_diterima_untuk_ketiga_nilai_valid(): void
    {
        foreach (['peserta', 'interview', 'laporan'] as $tab) {
            $this->actingAs($this->admin([
                'recruitment.periods.view',
                'recruitment.applications.list',
                'recruitment.interviews.schedule',
                'recruitment.reports.view',
            ]))
                ->get(route('dashboard.recruitment.periods.show', [
                    'period' => $this->period->id,
                    'tab' => $tab,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('tab', $tab));
        }
    }

    public function test_tab_invalid_fallback_ke_peserta_tanpa_error(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'hacker-value',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tab', 'peserta'));
    }

    public function test_tab_interview_hanya_memuat_sesi_periode_itu(): void
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $otherPeriod = RecruitmentPeriod::factory()->create();

        $admin = $this->admin(['recruitment.periods.view', 'recruitment.interviews.schedule']);

        $this->actingAs($admin)->post(route('dashboard.recruitment.interview-sessions.store'), [
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $division->id,
            'session_date' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '12:00',
            'location' => 'Gedung A',
            'room' => 'A101',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('dashboard.recruitment.interview-sessions.store'), [
            'recruitment_period_id' => $otherPeriod->id,
            'recruitment_division_id' => $division->id,
            'session_date' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '12:00',
            'location' => 'Gedung B',
            'room' => 'B202',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'interview']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'interview')
                ->where('sessions.total', 1)
                ->where('sessions.data.0.location', 'Gedung A')
                ->missing('applications'));
    }

    public function test_tab_laporan_ditolak_untuk_interviewer_only(): void
    {
        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        $this->actingAs($interviewer)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'laporan']))
            ->assertForbidden();
    }

    public function test_tab_laporan_memuat_report_periode_route_bukan_query(): void
    {
        $otherPeriod = RecruitmentPeriod::factory()->create();

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.reports.view']))
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'laporan']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'laporan')
                ->where('report.period.id', $this->period->id)
                ->missing('applications')
                ->missing('sessions'));
    }

    public function test_export_applicants_menetralkan_formula_csv(): void
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'full_name' => '=CMD(1)',
            'nim' => 'A11.2024.09999',
        ]);

        $content = $this->actingAs($this->admin(['recruitment.reports.export']))
            ->get(route('dashboard.recruitment.reports.export.applicants', ['period_id' => $this->period->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=CMD(1)", $content);
        $this->assertStringNotContainsString("\n=CMD(1)", $content);
    }

    public function test_export_applicants_menetralkan_formula_csv_dengan_spasi_depan(): void
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'full_name' => ' =CMD(1)',
            'nim' => 'A11.2024.09998',
        ]);

        $content = $this->actingAs($this->admin(['recruitment.reports.export']))
            ->get(route('dashboard.recruitment.reports.export.applicants', ['period_id' => $this->period->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("' =CMD(1)", $content);
    }

    public function test_laporan_global_redirect_ke_daftar_periode(): void
    {
        $this->actingAs($this->admin(['recruitment.reports.view']))
            ->get('/admin/recruitment/reports')
            ->assertRedirect(route('dashboard.recruitment.index'));
    }

    public function test_interview_sessions_index_redirect_ke_daftar_periode(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.interviews.schedule']))
            ->get('/admin/recruitment/interview-sessions')
            ->assertRedirect(route('dashboard.recruitment.index'));
    }

    public function test_tab_interview_dibuka_untuk_interviewer(): void
    {
        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        $this->actingAs($interviewer)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'interview']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tab', 'interview'));
    }

    public function test_tab_overlong_memicu_validation_error(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => str_repeat('x', 21),
            ]))
            ->assertSessionHasErrors('tab');
    }
}

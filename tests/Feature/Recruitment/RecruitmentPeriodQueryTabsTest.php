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
}

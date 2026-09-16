<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentPeriodApplicantListingTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private RecruitmentPeriod $otherPeriod;

    private RecruitmentApplication $budi;

    private RecruitmentApplication $siti;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $this->period = RecruitmentPeriod::factory()->create();
        $this->otherPeriod = RecruitmentPeriod::factory()->create();

        $this->budi = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'full_name' => 'Budi Santoso',
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'submitted_at' => now()->subDay(),
        ]);

        $this->siti = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'full_name' => 'Siti Aminah',
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'submitted_at' => now(),
        ]);

        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->otherPeriod->id,
            'primary_division_id' => $division->id,
            'full_name' => 'Applicant Periode Lain',
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'submitted_at' => now(),
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_period_show_lists_only_applications_of_that_period(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.recruitment.periods.show', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Recruitment/Periods/Show')
                ->where('applications.total', 2)
                ->where('applications.data.0.id', $this->siti->id)
                ->where('applications.data.1.id', $this->budi->id));
    }

    public function test_period_show_scopes_queue_counts_to_that_period(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.recruitment.periods.show', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('queue_counts.all', 2));
    }

    public function test_period_show_applies_search_inside_period(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period, 'search' => 'Budi']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.data.0.id', $this->budi->id));
    }

    public function test_staff_without_period_view_cannot_open_period_show(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $this->actingAs($staff)
            ->get(route('dashboard.recruitment.periods.show', $this->period))
            ->assertForbidden();
    }
}

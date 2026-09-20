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
            'semester' => 1,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'submitted_at' => now()->subDay(),
        ]);

        $this->siti = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'full_name' => 'Siti Aminah',
            'semester' => 3,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'submitted_at' => now(),
        ]);

        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'full_name' => 'Andi Wijaya',
            'semester' => 1,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'submitted_at' => now()->subDays(2),
        ]);

        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'full_name' => 'Dewi Lestari',
            'semester' => 3,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'submitted_at' => now()->subDays(3),
        ]);

        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->otherPeriod->id,
            'primary_division_id' => $division->id,
            'full_name' => 'Applicant Periode Lain',
            'semester' => 4,
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
                ->has('applications', 4)
                ->where('applications.0.id', $this->siti->id)
                ->where('applications.1.id', $this->budi->id));
    }

    public function test_period_show_scopes_queue_counts_to_that_period(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.recruitment.periods.show', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page                ->where('queue_counts.all', 4));
    }

    public function test_period_show_returns_full_list_search_is_filtered_in_frontend(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period, 'search' => 'Budi']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('applications', 4)
                ->where('applications.1.id', $this->budi->id));
    }

    public function test_user_tanpa_period_view_tidak_dapat_membuka_period_show(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('recruitment.dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard.recruitment.periods.show', $this->period))
            ->assertForbidden();
    }

    public function test_period_show_semester_options_are_distinct_and_ascending(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.recruitment.periods.show', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('semesterOptions', [
                ['value' => '1', 'label' => 'Semester 1'],
                ['value' => '3', 'label' => 'Semester 3'],
            ]));
    }

    public function test_period_show_semester_options_are_scoped_to_the_period(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.recruitment.periods.show', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('semesterOptions', [
                ['value' => '1', 'label' => 'Semester 1'],
                ['value' => '3', 'label' => 'Semester 3'],
            ]));

        $this->actingAs($this->admin())
            ->get(route('dashboard.recruitment.periods.show', $this->otherPeriod))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('semesterOptions', [
                ['value' => '4', 'label' => 'Semester 4'],
            ]));
    }

    public function test_period_show_semester_options_ignore_active_filters(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period,
                'semester' => '1',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('applications', 4)
                ->where('semesterOptions', [
                    ['value' => '1', 'label' => 'Semester 1'],
                    ['value' => '3', 'label' => 'Semester 3'],
                ]));
    }

    public function test_period_show_semester_options_empty_when_period_has_no_applications(): void
    {
        $emptyPeriod = RecruitmentPeriod::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('dashboard.recruitment.periods.show', $emptyPeriod))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('semesterOptions', []));
    }

    public function test_period_show_semester_options_empty_without_application_list_permission(): void
    {
        $periodViewer = User::factory()->create();
        $periodViewer->givePermissionTo([
            'recruitment.dashboard.view',
            'recruitment.periods.view',
        ]);

        $this->actingAs($periodViewer)
            ->get(route('dashboard.recruitment.periods.show', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('semesterOptions', [])
                ->where('applications', null));
    }
}

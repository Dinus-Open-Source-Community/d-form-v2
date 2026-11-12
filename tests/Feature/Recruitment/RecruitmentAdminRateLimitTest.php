<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

class RecruitmentAdminRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_be_p2_015_period_search_is_configured_and_isolated_per_user(): void
    {
        config(['recruitment.application_search_rate_limit_per_minute' => 1]);

        $owner = $this->recruitmentStaff();
        $owner->givePermissionTo('recruitment.periods.view');
        $period = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);
        $path = route('dashboard.recruitment.periods.show', $period).'?search=Applicant';

        $this->actingAs($owner)->get($path)->assertOk();
        $this->actingAs($owner)->get($path)->assertStatus(429)->assertHeader('Retry-After');

        $otherStaff = $this->recruitmentStaff();
        $otherStaff->givePermissionTo('recruitment.periods.view');

        $this->actingAs($otherStaff)->get($path)->assertOk();
    }

    public function test_be_p3_017_all_recruitment_exports_use_the_configured_limiter(): void
    {
        config(['recruitment.export_rate_limit_per_minute' => 1]);

        foreach ([
            'dashboard.recruitment.reports.export.funnel',
            'dashboard.recruitment.reports.export.applicants',
            'dashboard.recruitment.periods.interviews.export',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);

            self::assertInstanceOf(Route::class, $route);
            self::assertContains('throttle:recruitment-export', $route->middleware());
        }

        $owner = $this->recruitmentStaff();
        $period = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);
        $path = route('dashboard.recruitment.periods.interviews.export', $period);

        $this->actingAs($owner)->get($path)->assertOk();
        $this->actingAs($owner)->get($path)->assertStatus(429)->assertHeader('Retry-After');

        $otherStaff = $this->recruitmentStaff();
        $otherStaff->givePermissionTo('recruitment.periods.view');

        $this->actingAs($otherStaff)->get($path)->assertOk();
    }

    public function test_be_p2_020_send_qr_is_limited_per_user_and_period(): void
    {
        config(['recruitment.qr_send_rate_limit_per_minute' => 1]);

        $owner = $this->recruitmentStaff();
        $owner->givePermissionTo('recruitment.periods.edit');
        $firstPeriod = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);
        $secondPeriod = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);
        $firstPath = route('dashboard.recruitment.periods.send-qr', $firstPeriod);
        $secondPath = route('dashboard.recruitment.periods.send-qr', $secondPeriod);

        $this->actingAs($owner)->post($firstPath)->assertRedirect();
        $this->actingAs($owner)->post($firstPath)->assertStatus(429)->assertHeader('Retry-After');
        $this->actingAs($owner)->post($secondPath)->assertRedirect();

        $otherStaff = $this->recruitmentStaff();
        $otherStaff->givePermissionTo('recruitment.periods.edit');

        $this->actingAs($otherStaff)->post($firstPath)->assertRedirect();
    }

    public function test_be_p2_020_send_qr_forbids_staff_without_period_edit_permission(): void
    {
        $staff = $this->recruitmentStaff();
        $periodOwner = User::factory()->create();
        $period = RecruitmentPeriod::factory()->create(['created_by' => $periodOwner->id]);

        self::assertTrue($staff->can('recruitment.dashboard.view'));
        self::assertFalse($staff->can('recruitment.periods.edit'));

        $this->actingAs($staff)
            ->post(route('dashboard.recruitment.periods.send-qr', $period))
            ->assertForbidden();
    }

    public function test_be_p2_015_search_wildcards_are_literal_end_to_end(): void
    {
        $owner = $this->ownerWithViewPermission();
        $period = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);

        $division = RecruitmentDivision::factory()->create();
        $percent = $this->application($period->id, $division->id, 'Applicant 100% coverage');
        $underscore = $this->application($period->id, $division->id, 'Applicant under_score');
        $escape = $this->application($period->id, $division->id, 'Applicant !escape prefix');
        $this->application($period->id, $division->id, 'Applicant without symbols');

        $path = route('dashboard.recruitment.periods.show', $period);

        foreach ([
            '%' => $percent,
            '_' => $underscore,
            '!' => $escape,
        ] as $needle => $expected) {
            $this->actingAs($owner)
                ->get($path.'?'.http_build_query(['search' => $needle, 'tab' => 'peserta']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('applications.total', 1)
                    ->where('applications.data.0.id', $expected->id));
        }

        // Pencarian normal tetap substring biasa.
        $this->actingAs($owner)
            ->get($path.'?'.http_build_query(['search' => 'Applicant', 'tab' => 'peserta']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('applications.total', 4));
    }

    public function test_be_p2_015_search_throttle_is_applied_to_the_route_that_performs_the_search(): void
    {
        $route = app('router')->getRoutes()->getByName('dashboard.recruitment.periods.show');

        self::assertInstanceOf(Route::class, $route);
        self::assertContains('throttle:recruitment-application-search', $route->middleware());

        // Anggaran default 60/menit harus cukup untuk pemakaian wajar: pemuatan
        // halaman, polling QR tiap 5 detik, dan pencarian ber-debounce 300 ms.
        config(['recruitment.application_search_rate_limit_per_minute' => 60]);

        $owner = $this->ownerWithViewPermission();
        $period = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);
        $path = route('dashboard.recruitment.periods.show', $period);

        for ($request = 1; $request <= 60; $request++) {
            $this->actingAs($owner)->get($path)->assertOk();
        }

        $this->actingAs($owner)->get($path)->assertStatus(429)->assertHeader('Retry-After');
    }

    private function ownerWithViewPermission(): User
    {
        $owner = $this->recruitmentStaff();
        $owner->givePermissionTo('recruitment.periods.view');

        return $owner;
    }

    private function application(string $periodId, string $divisionId, string $fullName): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $periodId,
            'primary_division_id' => $divisionId,
            'full_name' => $fullName,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'submitted_at' => now(),
        ]);
    }

    private function recruitmentStaff(): User
    {
        $user = User::factory()->create();
        $user->assignRole('recruitment-staff');

        return $user;
    }
}

<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RecruitmentInterviewFlow;
use Tests\TestCase;

class InterviewAssignTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private User $staff;

    private User $superAdmin;

    private User $interviewer;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super-admin');

        $this->interviewer = User::factory()->create();
        $this->interviewer->assignRole('recruitment-interviewer');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->interviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

        $this->session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    public function test_assign_happy_path_sets_interviewer(): void
    {
        $interview = $this->unclaimedInterview('A1');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interviews.assign', $interview), [
                'interviewer_id' => $this->interviewer->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('recruitment_interviews', [
            'id' => $interview->id,
            'interviewer_id' => $this->interviewer->id,
            'status' => InterviewStatus::Waiting->value,
        ]);
    }

    public function test_assign_by_super_admin_allowed(): void
    {
        $interview = $this->unclaimedInterview('A2');

        $this->actingAs($this->superAdmin)
            ->post(route('dashboard.recruitment.interviews.assign', $interview), [
                'interviewer_id' => $this->interviewer->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->interviewer->id, RecruitmentInterview::query()->findOrFail($interview->id)->interviewer_id);
    }

    public function test_assign_forbidden_for_interviewer(): void
    {
        $interview = $this->unclaimedInterview('A3');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.interviews.assign', $interview), [
                'interviewer_id' => $this->interviewer->id,
            ])
            ->assertForbidden();

        $this->assertNull(RecruitmentInterview::query()->findOrFail($interview->id)->interviewer_id);
    }

    public function test_assign_rejected_when_already_claimed(): void
    {
        $interview = $this->unclaimedInterview('A4');

        RecruitmentInterview::query()->whereKey($interview->id)->update([
            'interviewer_id' => $this->interviewer->id,
            'status' => InterviewStatus::InProgress,
            'booked_at' => now(),
        ]);

        $other = User::factory()->create();
        $other->assignRole('recruitment-interviewer');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interviews.assign', $interview), [
                'interviewer_id' => $other->id,
            ])
            ->assertSessionHasErrors('interview_id');

        $this->assertSame($this->interviewer->id, RecruitmentInterview::query()->findOrFail($interview->id)->interviewer_id);
    }

    public function test_assign_rejected_for_non_primary_interview(): void
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-A5',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $secondary = RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $this->session->id,
            'interview_kind' => RecruitmentInterview::KIND_SECONDARY,
            'interviewer_id' => null,
            'scheduled_at' => now(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => InterviewStatus::Waiting,
        ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interviews.assign', $secondary), [
                'interviewer_id' => $this->interviewer->id,
            ])
            ->assertSessionHasErrors('interview_id');
    }

    public function test_pool_carries_division_interviewer_options(): void
    {
        $this->unclaimedInterview('A6');

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('primary_opportunities', 1)
                ->where('primary_opportunities.0.interviewer_options.0.value', $this->interviewer->id)
                ->where('primary_opportunities.0.interviewer_options.0.label', $this->interviewer->name)
                ->where('can_assign_interviewer', false));
    }

    public function test_staff_sees_assign_flag(): void
    {
        // Staff tanpa evaluations.view tak bisa buka index; super-admin bisa.
        $this->actingAs($this->superAdmin)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can_assign_interviewer', true));
    }

    private function unclaimedInterview(string $suffix): RecruitmentInterview
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->checkInApplicant($this->session, $application, $this->staff);

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->update([
                'interviewer_id' => null,
                'status' => InterviewStatus::Waiting,
                'scheduled_at' => now()->subMinute(),
            ]);

        return RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->firstOrFail();
    }
}

<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RecruitmentInterviewFlow;
use Tests\TestCase;

class RecruitmentMyInterviewScopeTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private User $staff;

    private User $interviewer;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        Queue::fake();

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

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

    private function application(string $suffix): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-MI'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);
    }

    public function test_index_only_lists_booked_in_progress_applicants(): void
    {
        $booked = $this->application('001');
        $waitingOnly = $this->application('002');

        $this->checkInApplicant($this->session, $booked, $this->staff);
        $this->checkInApplicant($this->session, $waitingOnly, $this->staff);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.book', $booked))
            ->assertRedirect();

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Recruitment/MyInterviews/Index')
                ->where('interviews.total', 1)
                ->has('interviews.data', 1)
                ->where('interviews.data.0.application.id', $booked->id));
    }

    public function test_done_tab_lists_evaluated_applicants(): void
    {
        $applicant = $this->application('010');
        $this->checkInApplicant($this->session, $applicant, $this->staff);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.book', $applicant))
            ->assertRedirect();

        $interview = RecruitmentInterview::query()
            ->where('recruitment_application_id', $applicant->id)
            ->firstOrFail();

        RecruitmentEvaluation::query()->create([
            'recruitment_application_id' => $applicant->id,
            'recruitment_interview_id' => $interview->id,
            'speaking_score' => 8,
            'technical_score' => 8,
            'attitude_score' => 8,
            'recommendation' => EvaluationRecommendation::Recommended->value,
            'evaluated_by' => $this->interviewer->id,
            'evaluated_at' => now(),
        ]);

        $interview->update(['status' => InterviewStatus::Completed]);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'done']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('interviews.total', 1));
    }
}

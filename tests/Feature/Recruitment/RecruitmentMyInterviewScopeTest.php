<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\AttendanceMethod;
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
use App\Services\Recruitment\AttendanceService;
use App\Services\Recruitment\InterviewSchedulingService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecruitmentMyInterviewScopeTest extends TestCase
{
    use RefreshDatabase;

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

    private function scheduledApplication(string $suffix): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-MI'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        app(InterviewSchedulingService::class)->scheduleApplicants(
            $this->staff,
            $this->session,
            [$application->id],
        );

        $interview = RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->firstOrFail();

        $interview->update(['interviewer_id' => $this->interviewer->id]);

        return $application->fresh(['interview']);
    }

    private function checkIn(RecruitmentApplication $application): void
    {
        app(AttendanceService::class)->checkIn(
            $this->session,
            $application,
            AttendanceMethod::RegistrationNumber,
            $this->staff,
        );
    }

    private function evaluate(RecruitmentApplication $application): void
    {
        $interview = $application->fresh(['interview'])->interview;
        $this->assertNotNull($interview);

        RecruitmentEvaluation::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_id' => $interview->id,
            'speaking_score' => 8,
            'technical_score' => 8,
            'attitude_score' => 8,
            'recommendation' => EvaluationRecommendation::Recommended->value,
            'evaluated_by' => $this->interviewer->id,
            'evaluated_at' => now(),
        ]);
    }

    public function test_index_only_lists_applicants_who_checked_in(): void
    {
        $attended = $this->scheduledApplication('001');
        $absent = $this->scheduledApplication('002');

        $this->checkIn($attended);

        // Efek samping status (bukan attendance) tidak boleh membuat applicant muncul.
        RecruitmentInterview::query()
            ->where('recruitment_application_id', $absent->id)
            ->update(['status' => InterviewStatus::Queued]);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Recruitment/MyInterviews/Index')
                ->where('interviews.total', 1)
                ->has('interviews.data', 1)
                ->where('interviews.data.0.application.id', $attended->id)
                ->where('queue_counts.all', 1)
                ->where('queue_counts.pending', 1)
                ->where('queue_counts.today', 1)
                ->where('queue_counts.done', 0)
                ->has('today_sessions', 1)
                ->where('today_sessions.0.my_interviews_count', 1)
                ->has('division_options', 1)
                ->where('division_options.0.value', $this->programming->id)
                ->has('session_options', 1)
                ->where('session_options.0.value', $this->session->id));
    }

    public function test_index_includes_applicant_after_check_in_and_keeps_queue_tabs(): void
    {
        $this->scheduledApplication('003');
        $applicant = $this->scheduledApplication('004');

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 0)
                ->where('queue_counts.all', 0)
                ->where('queue_counts.pending', 0)
                ->where('queue_counts.today', 0)
                ->where('queue_counts.done', 0)
                ->has('today_sessions', 0)
                ->has('division_options', 0)
                ->has('session_options', 0));

        $this->checkIn($applicant);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $applicant->id)
                ->where('queue_counts.all', 1)
                ->where('queue_counts.pending', 1)
                ->has('today_sessions', 1)
                ->where('today_sessions.0.my_interviews_count', 1)
                ->has('division_options', 1)
                ->has('session_options', 1));

        $this->evaluate($applicant);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['queue' => 'pending']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 0)
                ->where('queue_counts.pending', 0)
                ->where('queue_counts.done', 1));

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['queue' => 'done']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->where('interviews.data.0.application.id', $applicant->id));
    }
}

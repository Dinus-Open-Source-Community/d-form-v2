<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Enums\Recruitment\MembershipType;
use App\Enums\Recruitment\ScreeningReason;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentFeedback;
use App\Models\Recruitment\RecruitmentFinalDecision;
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

class RecruitmentWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private const TRACKING_TOKEN = 'workflow-happy-path-token-xyz';

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

    public function test_happy_path_from_screening_to_feedback(): void
    {
        $application = RecruitmentApplication::factory()
            ->for($this->period, 'period')
            ->withTrackingToken(self::TRACKING_TOKEN)
            ->create([
                'registration_number' => 'OPREC-2026-WF001',
                'primary_division_id' => $this->programming->id,
                'stage' => ApplicationStage::Submitted,
                'result' => ApplicationResult::Pending,
            ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.pass', $application))
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(ApplicationStage::Interview, $application->stage);

        $this->checkInApplicant($this->session, $application, $this->staff);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.book', $application))
            ->assertRedirect();

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), [
                'speaking_score' => 8,
                'technical_score' => 9,
                'attitude_score' => 8,
                'recommendation' => EvaluationRecommendation::Recommended->value,
                'notes' => 'Recommended for AA.',
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(ApplicationStage::FinalReview, $application->stage);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->programming->id,
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(ApplicationStage::Completed, $application->stage);
        $this->assertSame(ApplicationResult::Accepted, $application->result);

        $decision = RecruitmentFinalDecision::query()
            ->where('recruitment_application_id', $application->id)
            ->first();

        $this->assertNotNull($decision);

        $this->post(route('recruitment.track.authenticate'), [
            'registration_number' => $application->registration_number,
            'tracking_token' => self::TRACKING_TOKEN,
        ])->assertRedirect(route('recruitment.track.show'));

        $this->post(route('recruitment.track.feedback.store'), [
            'rating_registration_ease' => 5,
            'rating_info_clarity' => 5,
            'rating_tracking_ease' => 5,
            'rating_interview_experience' => 5,
            'rating_staff_service' => 5,
            'feedback_text' => 'Great experience.',
        ])->assertRedirect();

        $this->assertNotNull(
            RecruitmentFeedback::query()->where('recruitment_application_id', $application->id)->first()
        );
    }

    public function test_revision_flow(): void
    {
        $application = RecruitmentApplication::factory()
            ->for($this->period, 'period')
            ->create([
                'primary_division_id' => $this->programming->id,
                'stage' => ApplicationStage::Submitted,
            ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.revision', $application), [
                'reason' => ScreeningReason::IncompleteData->value,
                'sections' => ['cv'],
                'notes' => 'Please re-upload CV.',
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertTrue($application->revision_required);
    }
}

<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentEvaluation;
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

class RecruitmentEvaluationTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private User $staff;

    private User $interviewer;

    private User $otherInterviewer;

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

        $this->otherInterviewer = User::factory()->create();
        $this->otherInterviewer->assignRole('recruitment-interviewer');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->interviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->otherInterviewer->id,
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

    private function createBookedApplication(string $suffix = '1'): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-EV'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->checkInApplicant($this->session, $application, $this->staff);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.book', $application))
            ->assertRedirect();

        return $application->fresh(['interview']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validEvaluationPayload(): array
    {
        return [
            'speaking_score' => 8,
            'technical_score' => 7,
            'attitude_score' => 9,
            'recommendation' => EvaluationRecommendation::Recommended->value,
            'notes' => 'Good communication skills.',
        ];
    }

    public function test_interviewer_submit_scores_saves_evaluation(): void
    {
        $application = $this->createBookedApplication('1');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $this->validEvaluationPayload())
            ->assertRedirect(route('dashboard.recruitment.my-interviews.show', $application));

        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
            'speaking_score' => 8,
        ]);
    }

    public function test_interviewer_cannot_evaluate_unassigned_application(): void
    {
        $application = $this->createBookedApplication('3');

        $this->actingAs($this->otherInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $this->validEvaluationPayload())
            ->assertForbidden();
    }

    public function test_interviewer_cannot_edit_evaluation_after_lock(): void
    {
        $application = $this->createBookedApplication('5');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $this->validEvaluationPayload());

        $evaluation = RecruitmentEvaluation::query()
            ->where('recruitment_application_id', $application->id)
            ->firstOrFail();

        $this->assertNotNull($evaluation->locked_at);

        $updated = $this->validEvaluationPayload();
        $updated['speaking_score'] = 5;

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $updated)
            ->assertForbidden();
    }

    public function test_staff_can_override_locked_evaluation_with_audit(): void
    {
        $application = $this->createBookedApplication('6');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $this->validEvaluationPayload());

        $override = $this->validEvaluationPayload();
        $override['technical_score'] = 10;

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.evaluation.override', $application), $override)
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
            'technical_score' => 10,
        ]);
    }

    public function test_interviewer_can_access_my_interviews_index(): void
    {
        $this->createBookedApplication('9');

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk();
    }
}

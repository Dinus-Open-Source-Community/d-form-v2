<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentActivityLog;
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

class RecruitmentEvaluationTest extends TestCase
{
    use RefreshDatabase;

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

    private function createAssignedApplication(string $suffix = '1'): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-0'.$suffix,
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
        $application = $this->createAssignedApplication('1');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $this->validEvaluationPayload())
            ->assertRedirect(route('dashboard.recruitment.my-interviews.show', $application));

        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
            'speaking_score' => 8,
            'technical_score' => 7,
            'attitude_score' => 9,
            'recommendation' => EvaluationRecommendation::Recommended->value,
        ]);
    }

    public function test_score_out_of_range_is_rejected(): void
    {
        $application = $this->createAssignedApplication('2');

        $payload = $this->validEvaluationPayload();
        $payload['speaking_score'] = 11;

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $payload)
            ->assertSessionHasErrors('speaking_score');

        $payload['speaking_score'] = 0;

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $payload)
            ->assertSessionHasErrors('speaking_score');
    }

    public function test_interviewer_cannot_evaluate_unassigned_application(): void
    {
        $application = $this->createAssignedApplication('3');

        $this->actingAs($this->otherInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $this->validEvaluationPayload())
            ->assertForbidden();
    }

    public function test_interviewer_can_edit_evaluation_before_lock(): void
    {
        $application = $this->createAssignedApplication('4');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $this->validEvaluationPayload());

        $updated = $this->validEvaluationPayload();
        $updated['speaking_score'] = 6;
        $updated['notes'] = 'Updated after reflection.';

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $updated)
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
            'speaking_score' => 6,
        ]);
    }

    public function test_interviewer_cannot_edit_evaluation_after_lock(): void
    {
        $application = $this->createAssignedApplication('5');
        $applicationB = $this->createAssignedApplication('51');

        app(AttendanceService::class)->checkInFromInput(
            $this->session,
            $application->registration_number,
            null,
            null,
            $this->staff,
        );

        app(AttendanceService::class)->checkInFromInput(
            $this->session,
            $applicationB->registration_number,
            null,
            null,
            $this->staff,
        );

        $this->actingAs($this->staff)
            ->postJson(route('dashboard.recruitment.queue.call-next', $this->session))
            ->assertOk();

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $this->validEvaluationPayload());

        $this->actingAs($this->staff)
            ->postJson(route('dashboard.recruitment.queue.call-next', $this->session))
            ->assertOk();

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
        $application = $this->createAssignedApplication('6');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), $this->validEvaluationPayload());

        RecruitmentEvaluation::query()
            ->where('recruitment_application_id', $application->id)
            ->update(['locked_at' => now()]);

        $override = $this->validEvaluationPayload();
        $override['technical_score'] = 10;

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.evaluation.override', $application), $override)
            ->assertRedirect(route('dashboard.recruitment.applications.show', $application));

        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
            'technical_score' => 10,
        ]);

        $this->assertDatabaseHas('recruitment_activity_logs', [
            'recruitment_application_id' => $application->id,
            'action' => 'evaluation.staff_override',
        ]);
    }

    public function test_interviewer_can_evaluate_assigned_application_page(): void
    {
        $application = $this->createAssignedApplication('7');

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.show', $application))
            ->assertOk();
    }

    public function test_interviewer_cannot_download_cv_of_unassigned_application(): void
    {
        $application = $this->createAssignedApplication('8');

        $this->actingAs($this->otherInterviewer)
            ->get(route('dashboard.recruitment.applications.documents.download', [
                'application' => $application,
                'type' => 'cv',
            ]))
            ->assertForbidden();
    }

    public function test_interviewer_can_access_my_interviews_index(): void
    {
        $this->createAssignedApplication('9');

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk();
    }

    public function test_interviewer_cannot_access_staff_applicant_list(): void
    {
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.periods.show', $this->period->id))
            ->assertForbidden();
    }

    public function test_interviewer_cannot_staff_override_evaluation(): void
    {
        $application = $this->createAssignedApplication('0');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.applications.evaluation.override', $application), $this->validEvaluationPayload())
            ->assertForbidden();
    }
}

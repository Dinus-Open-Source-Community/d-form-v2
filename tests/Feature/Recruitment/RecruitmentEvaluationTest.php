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

    /**
     * Pre-assign applicant ke owner via DB (pengganti endpoint book).
     */
    private function createBookedApplication(string $suffix = '1', ?User $owner = null): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-EV'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->checkInApplicant($this->session, $application, $this->staff);

        // Pre-assign: interviewer langsung ditempel di DB (endpoint book dibuang).
        RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->update([
                'interviewer_id' => ($owner ?? $this->interviewer)->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
                'scheduled_at' => now()->subHour(),
            ]);

        return $application->fresh(['primaryInterview']);
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
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $this->validEvaluationPayload())
            ->assertRedirect(route('dashboard.recruitment.my-interviews.index', ['tab' => 'done']))
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
            'speaking_score' => 8,
        ]);
    }

    public function test_interviewer_cannot_evaluate_unassigned_application(): void
    {
        $application = $this->createBookedApplication('3');

        $this->actingAs($this->otherInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $this->validEvaluationPayload())
            ->assertForbidden();
    }

    public function test_interviewer_cannot_edit_evaluation_after_budget_exhausted(): void
    {
        $application = $this->createBookedApplication('5');

        for ($i = 1; $i <= 3; $i++) {
            $payload = $this->validEvaluationPayload();
            $payload['speaking_score'] = 4 + $i;

            $this->actingAs($this->interviewer)
                ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $payload)
                ->assertRedirect();
        }

        $evaluation = RecruitmentEvaluation::query()
            ->where('recruitment_application_id', $application->id)
            ->firstOrFail();

        $this->assertSame(3, $evaluation->save_count);
        $this->assertNotNull($evaluation->locked_at);

        $updated = $this->validEvaluationPayload();
        $updated['speaking_score'] = 5;

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $updated)
            ->assertForbidden();
    }

    public function test_staff_can_override_unlocked_evaluation_with_audit(): void
    {
        $application = $this->createBookedApplication('6');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $this->validEvaluationPayload());

        $override = $this->validEvaluationPayload();
        $override['technical_score'] = 10;

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interviews.evaluation.override', $application->primaryInterview), $override)
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
            'technical_score' => 10,
            'save_count' => 2,
        ]);
    }

    public function test_evaluate_rejected_while_earlier_applicant_pending(): void
    {
        $earlier = $this->createBookedApplication('SQ1');
        $earlier->primaryInterview->update(['scheduled_at' => now()->subHours(2)]);
        $later = $this->createBookedApplication('SQ2');

        $this->actingAs($this->interviewer)
            ->postJson(route('dashboard.recruitment.my-interviews.evaluate', $later->primaryInterview), $this->validEvaluationPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('evaluation');

        $this->assertDatabaseMissing('recruitment_evaluations', [
            'recruitment_application_id' => $later->id,
        ]);
    }

    public function test_evaluate_allowed_after_earlier_applicant_scored(): void
    {
        $earlier = $this->createBookedApplication('SQ3');
        $earlier->primaryInterview->update(['scheduled_at' => now()->subHours(2)]);
        $later = $this->createBookedApplication('SQ4');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $earlier->primaryInterview), $this->validEvaluationPayload())
            ->assertRedirect();

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $later->primaryInterview), $this->validEvaluationPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $earlier->id,
        ]);
        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $later->id,
        ]);
    }

    public function test_staff_override_bypasses_sequential_guard(): void
    {
        // Pending milik staff dengan jadwal lebih awal.
        $pending = $this->createBookedApplication('SQ5', $this->staff);
        $pending->primaryInterview->update(['scheduled_at' => now()->subHours(2)]);

        // Applicant lain dinilai interviewer-nya lalu dikunci.
        $locked = $this->createBookedApplication('SQ6');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $locked->primaryInterview), $this->validEvaluationPayload())
            ->assertRedirect();

        // Override staff tetap lolos walau staff punya pending lebih awal.
        $override = $this->validEvaluationPayload();
        $override['technical_score'] = 10;

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interviews.evaluation.override', $locked->primaryInterview), $override)
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $locked->id,
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
    public function test_submit_recommended_moves_application_to_final_review(): void
    {
        $application = $this->createBookedApplication('30');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $this->validEvaluationPayload())
            ->assertRedirect(route('dashboard.recruitment.my-interviews.index', ['tab' => 'done']))
            ->assertSessionHas('toast');

        $application->refresh();
        $this->assertSame(ApplicationStage::FinalReview, $application->stage);
        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
            'recommendation' => EvaluationRecommendation::Recommended->value,
        ]);
    }

    public function test_submit_not_recommended_moves_application_to_final_review(): void
    {
        $application = $this->createBookedApplication('31');

        $payload = $this->validEvaluationPayload();
        $payload['recommendation'] = EvaluationRecommendation::NotRecommended->value;

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $payload)
            ->assertRedirect(route('dashboard.recruitment.my-interviews.index', ['tab' => 'done']))
            ->assertSessionHas('toast');

        $application->refresh();
        $this->assertSame(ApplicationStage::FinalReview, $application->stage);
        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
            'recommendation' => EvaluationRecommendation::NotRecommended->value,
        ]);
    }

    public function test_evaluation_requires_notes_for_both_recommendations(): void
    {
        $application = $this->createBookedApplication('34');

        $recommended = $this->validEvaluationPayload();
        unset($recommended['notes']);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $recommended)
            ->assertSessionHasErrors('notes');

        $notRecommended = $this->validEvaluationPayload();
        $notRecommended['recommendation'] = EvaluationRecommendation::NotRecommended->value;
        unset($notRecommended['notes']);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $notRecommended)
            ->assertSessionHasErrors('notes');

        $tooShort = $this->validEvaluationPayload();
        $tooShort['notes'] = 'Bagus';

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $tooShort)
            ->assertSessionHasErrors('notes');

        $this->assertDatabaseMissing('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
        ]);
    }

    public function test_submit_on_completed_stage_keeps_stage(): void
    {
        $application = $this->createBookedApplication('32');
        $application->update(['stage' => ApplicationStage::Completed]);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $this->validEvaluationPayload())
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(ApplicationStage::Completed, $application->stage);
        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
        ]);
    }

    public function test_staff_override_on_locked_evaluation_moves_to_final_review(): void
    {
        $application = $this->createBookedApplication('33');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $this->validEvaluationPayload())
            ->assertRedirect();

        // Simulasi data lama yang dinilai sebelum transisi otomatis ada.
        $application->update(['stage' => ApplicationStage::Interview]);

        $override = $this->validEvaluationPayload();
        $override['technical_score'] = 10;

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interviews.evaluation.override', $application->primaryInterview), $override)
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(ApplicationStage::FinalReview, $application->stage);
        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_application_id' => $application->id,
            'technical_score' => 10,
        ]);
    }
}

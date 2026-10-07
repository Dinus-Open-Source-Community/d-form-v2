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
use Tests\Support\RecruitmentInterviewFlow;
use Tests\TestCase;

class SecondaryInterviewTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private User $staff;

    private User $primaryInterviewer;

    private User $secondaryInterviewer;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentDivision $data;

    private RecruitmentInterviewSession $programmingSession;

    private RecruitmentInterviewSession $dataSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->primaryInterviewer = User::factory()->create();
        $this->primaryInterviewer->assignRole('recruitment-interviewer');

        $this->secondaryInterviewer = User::factory()->create();
        $this->secondaryInterviewer->assignRole('recruitment-interviewer');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->data = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->primaryInterviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->secondaryInterviewer->id,
            'recruitment_division_id' => $this->data->id,
        ]);

        $this->programmingSession = $this->makeSession($this->programming->id);
        $this->dataSession = $this->makeSession($this->data->id);
    }

    public function test_claim_happy_path_creates_secondary_interview(): void
    {
        $application = $this->evaluatedPrimaryApplication('C1');

        $response = $this->actingAs($this->secondaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), [
                'application_id' => $application->id,
                'session_id' => $this->dataSession->id,
            ]);

        $secondary = RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->where('interview_kind', 'secondary')
            ->firstOrFail();

        $response->assertRedirect(route('dashboard.recruitment.my-interviews.show', $secondary));

        $this->assertDatabaseHas('recruitment_interviews', [
            'recruitment_application_id' => $application->id,
            'interview_kind' => 'secondary',
            'interviewer_id' => $this->secondaryInterviewer->id,
            'recruitment_interview_session_id' => $this->dataSession->id,
        ]);
    }

    public function test_claim_rejected_without_secondary_id(): void
    {
        $application = $this->evaluatedPrimaryApplication('C2', withSecondary: false);

        $this->actingAs($this->secondaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), [
                'application_id' => $application->id,
                'session_id' => $this->dataSession->id,
            ])
            ->assertSessionHasErrors('application_id');

        $this->assertDatabaseMissing('recruitment_interviews', [
            'recruitment_application_id' => $application->id,
            'interview_kind' => 'secondary',
        ]);
    }

    public function test_claim_rejected_when_primary_unevaluated(): void
    {
        $application = $this->assignedApplication('C3');

        $this->actingAs($this->secondaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), [
                'application_id' => $application->id,
                'session_id' => $this->dataSession->id,
            ])
            ->assertSessionHasErrors('application_id');

        $this->assertDatabaseMissing('recruitment_interviews', [
            'recruitment_application_id' => $application->id,
            'interview_kind' => 'secondary',
        ]);
    }

    public function test_second_claim_rejected(): void
    {
        $application = $this->evaluatedPrimaryApplication('C4');

        $claim = [
            'application_id' => $application->id,
            'session_id' => $this->dataSession->id,
        ];

        $this->actingAs($this->secondaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), $claim)
            ->assertRedirect();

        $this->actingAs($this->secondaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), $claim)
            ->assertSessionHasErrors('application_id');

        $this->assertSame(1, RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->where('interview_kind', 'secondary')
            ->count());
    }

    public function test_claim_rejected_for_wrong_or_inactive_session(): void
    {
        $application = $this->evaluatedPrimaryApplication('C5');

        $this->actingAs($this->secondaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), [
                'application_id' => $application->id,
                'session_id' => $this->programmingSession->id,
            ])
            ->assertSessionHasErrors('session_id');

        $inactive = $this->makeSession($this->data->id, isActive: false);

        $this->actingAs($this->secondaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), [
                'application_id' => $application->id,
                'session_id' => $inactive->id,
            ])
            ->assertSessionHasErrors('session_id');

        $this->assertDatabaseMissing('recruitment_interviews', [
            'recruitment_application_id' => $application->id,
            'interview_kind' => 'secondary',
        ]);
    }

    public function test_claim_rejected_for_non_secondary_division_interviewer(): void
    {
        $application = $this->evaluatedPrimaryApplication('C6');

        // Interviewer primary bukan bagian divisi secondary.
        $this->actingAs($this->primaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), [
                'application_id' => $application->id,
                'session_id' => $this->dataSession->id,
            ])
            ->assertForbidden();

        // Punya role interviewer tapi tak terdaftar di divisi mana pun.
        $outsider = User::factory()->create();
        $outsider->assignRole('recruitment-interviewer');

        $this->actingAs($outsider)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), [
                'application_id' => $application->id,
                'session_id' => $this->dataSession->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('recruitment_interviews', [
            'recruitment_application_id' => $application->id,
            'interview_kind' => 'secondary',
        ]);
    }

    public function test_secondary_show_displays_secondary_context_and_empty_form(): void
    {
        [$application, $secondary] = $this->claimedSecondary('C7');

        $this->actingAs($this->secondaryInterviewer)
            ->get(route('dashboard.recruitment.my-interviews.show', $secondary))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('detail.interview.session.division', 'Data')
                ->where('detail.application.secondary_division', 'Data')
                ->where('detail.evaluation.can_edit', true));
    }

    public function test_evaluate_on_secondary_saves_against_secondary_interview(): void
    {
        [$application, $secondary] = $this->claimedSecondary('C8');

        $this->actingAs($this->secondaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $secondary), [
                'speaking_score' => 6,
                'technical_score' => 6,
                'attitude_score' => 6,
                'recommendation' => EvaluationRecommendation::NotRecommended->value,
                'notes' => 'Kurang cocok untuk kebutuhan divisi data.',
            ])
            ->assertRedirect(route('dashboard.recruitment.my-interviews.index', ['tab' => 'done']))
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('recruitment_evaluations', [
            'recruitment_interview_id' => $secondary->id,
            'recommendation' => EvaluationRecommendation::NotRecommended->value,
        ]);

        $this->assertSame(1, RecruitmentEvaluation::query()
            ->where('recruitment_application_id', $application->id)
            ->where('recommendation', EvaluationRecommendation::Recommended->value)
            ->count());
    }

    public function test_primary_interviewer_forbidden_on_secondary_interview(): void
    {
        [$application, $secondary] = $this->claimedSecondary('C9');

        $this->actingAs($this->primaryInterviewer)
            ->get(route('dashboard.recruitment.my-interviews.show', $secondary))
            ->assertForbidden();

        $this->actingAs($this->primaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $secondary), [
                'speaking_score' => 9,
                'technical_score' => 9,
                'attitude_score' => 9,
                'recommendation' => EvaluationRecommendation::Recommended->value,
                'notes' => 'Mencoba menilai interview divisi lain.',
            ])
            ->assertForbidden();
    }

    public function test_legacy_application_show_redirects_to_primary_interview(): void
    {
        $application = $this->evaluatedPrimaryApplication('C10');

        $primary = RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->where('interview_kind', 'primary')
            ->firstOrFail();

        $this->actingAs($this->primaryInterviewer)
            ->get('/admin/recruitment/my-interviews/'.$application->id)
            ->assertRedirect(route('dashboard.recruitment.my-interviews.show', $primary));
    }

    public function test_index_lists_opportunities_excluding_applicants_without_secondary(): void
    {
        $eligible = $this->evaluatedPrimaryApplication('C11');
        $this->evaluatedPrimaryApplication('C12', withSecondary: false);

        $this->actingAs($this->secondaryInterviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('secondary_opportunities', 1)
                ->where('secondary_opportunities.0.application.id', $eligible->id));
    }

    public function test_index_lists_claimed_secondary_outside_primary_list(): void
    {
        [$application, $secondary] = $this->claimedSecondary('C13');

        $this->actingAs($this->secondaryInterviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 0)
                ->has('claimed_secondary', 1)
                ->where('claimed_secondary.0.interview_id', $secondary->id));
    }

    /**
     * @return array{0: RecruitmentApplication, 1: RecruitmentInterview}
     */
    private function claimedSecondary(string $suffix): array
    {
        $application = $this->evaluatedPrimaryApplication($suffix);

        $this->actingAs($this->secondaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.secondary-claim'), [
                'application_id' => $application->id,
                'session_id' => $this->dataSession->id,
            ])
            ->assertRedirect();

        $secondary = RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->where('interview_kind', 'secondary')
            ->firstOrFail();

        return [$application->fresh(), $secondary];
    }

    private function assignedApplication(string $suffix, bool $withSecondary = true): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'secondary_division_id' => $withSecondary ? $this->data->id : null,
            'registration_number' => 'OPREC-2026-SC'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->checkInApplicant($this->programmingSession, $application, $this->staff);

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->update([
                'interviewer_id' => $this->primaryInterviewer->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
                'scheduled_at' => now()->subHour(),
            ]);

        return $application->fresh(['primaryInterview']);
    }

    private function evaluatedPrimaryApplication(string $suffix, bool $withSecondary = true): RecruitmentApplication
    {
        $application = $this->assignedApplication($suffix, $withSecondary);

        $this->actingAs($this->primaryInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), [
                'speaking_score' => 8,
                'technical_score' => 7,
                'attitude_score' => 9,
                'recommendation' => EvaluationRecommendation::Recommended->value,
                'notes' => 'Komunikatif dan menguasai dasar divisi.',
            ])
            ->assertRedirect();

        return $application->fresh();
    }

    private function makeSession(string $divisionId, bool $isActive = true): RecruitmentInterviewSession
    {
        return RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $divisionId,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => $isActive,
        ]);
    }
}

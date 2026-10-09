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

class PrimaryClaimTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private User $staff;

    private User $interviewer;

    private User $otherInterviewer;

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

        $this->interviewer = User::factory()->create();
        $this->interviewer->assignRole('recruitment-interviewer');

        $this->otherInterviewer = User::factory()->create();
        $this->otherInterviewer->assignRole('recruitment-interviewer');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->data = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->interviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->otherInterviewer->id,
            'recruitment_division_id' => $this->data->id,
        ]);

        $this->programmingSession = $this->makeSession($this->programming->id);
        $this->dataSession = $this->makeSession($this->data->id);
    }

    public function test_claim_happy_path_assigns_interviewer(): void
    {
        $interview = $this->waitingCheckedInInterview('P1');

        $response = $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.primary-claim'), [
                'interview_id' => $interview->id,
            ]);

        $claimed = RecruitmentInterview::query()->findOrFail($interview->id);

        $response->assertRedirect(route('dashboard.recruitment.my-interviews.show', $claimed));

        $this->assertSame($this->interviewer->id, $claimed->interviewer_id);
        $this->assertSame(InterviewStatus::InProgress, $claimed->status);
        $this->assertNotNull($claimed->booked_at);
    }

    public function test_claimed_interview_appears_in_my_list(): void
    {
        $interview = $this->waitingCheckedInInterview('P2');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.primary-claim'), [
                'interview_id' => $interview->id,
            ])
            ->assertRedirect();

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index', ['tab' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('interviews.total', 1)
                ->has('primary_opportunities', 0));
    }

    public function test_index_lists_primary_opportunities_in_my_division(): void
    {
        $mine = $this->waitingCheckedInInterview('P3');
        $this->waitingCheckedInInterview('P4', $this->dataSession, $this->data->id);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('primary_opportunities', 1)
                ->where('primary_opportunities.0.interview.id', $mine->id)
                ->where('primary_opportunities.0.application.id', $mine->recruitment_application_id));
    }

    public function test_claim_rejected_for_wrong_division_interviewer(): void
    {
        $interview = $this->waitingCheckedInInterview('P5');

        // Interviewer divisi data bukan bagian divisi sesi programming.
        $this->actingAs($this->otherInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.primary-claim'), [
                'interview_id' => $interview->id,
            ])
            ->assertForbidden();

        // Punya role interviewer tapi tak terdaftar di divisi mana pun.
        $outsider = User::factory()->create();
        $outsider->assignRole('recruitment-interviewer');

        $this->actingAs($outsider)
            ->post(route('dashboard.recruitment.my-interviews.primary-claim'), [
                'interview_id' => $interview->id,
            ])
            ->assertForbidden();

        $this->assertNull(RecruitmentInterview::query()->findOrFail($interview->id)->interviewer_id);
    }

    public function test_claim_rejected_without_submit_permission(): void
    {
        $interview = $this->waitingCheckedInInterview('P6');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.my-interviews.primary-claim'), [
                'interview_id' => $interview->id,
            ])
            ->assertForbidden();

        $this->assertNull(RecruitmentInterview::query()->findOrFail($interview->id)->interviewer_id);
    }

    public function test_claim_already_claimed_rejected(): void
    {
        $interview = $this->waitingCheckedInInterview('P7');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.primary-claim'), [
                'interview_id' => $interview->id,
            ])
            ->assertRedirect();

        $this->actingAs($this->otherInterviewer)
            ->post(route('dashboard.recruitment.my-interviews.primary-claim'), [
                'interview_id' => $interview->id,
            ])
            ->assertSessionHasErrors('interview_id');

        $this->assertSame($this->interviewer->id, RecruitmentInterview::query()->findOrFail($interview->id)->interviewer_id);
    }

    public function test_claim_rejected_without_check_in(): void
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-PC8',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $interview = RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $this->programmingSession->id,
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'interviewer_id' => null,
            'scheduled_at' => now()->subHour(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => InterviewStatus::Waiting,
        ]);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.primary-claim'), [
                'interview_id' => $interview->id,
            ])
            ->assertSessionHasErrors('interview_id');

        $this->assertNull(RecruitmentInterview::query()->findOrFail($interview->id)->interviewer_id);
    }

    private function waitingCheckedInInterview(
        string $suffix,
        ?RecruitmentInterviewSession $session = null,
        ?string $divisionId = null,
    ): RecruitmentInterview {
        $session ??= $this->programmingSession;

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $divisionId ?? $this->programming->id,
            'registration_number' => 'OPREC-2026-PC'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->checkInApplicant($session, $application, $this->staff);

        // checkIn membuat waiting interview tanpa interviewer; jadwal
        // dimundurkan agar lolos startedScope pada daftar My Interviews.
        RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->update([
                'interviewer_id' => null,
                'status' => InterviewStatus::Waiting,
                'scheduled_at' => now()->subHour(),
            ]);

        return RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->firstOrFail();
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

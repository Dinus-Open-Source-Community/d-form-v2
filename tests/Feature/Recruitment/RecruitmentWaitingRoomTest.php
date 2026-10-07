<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Enums\Recruitment\InterviewStatus;
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

class RecruitmentWaitingRoomTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private User $interviewer;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->interviewer = User::factory()->create();
        $this->interviewer->assignRole('recruitment-interviewer');

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->interviewer->id,
            'recruitment_division_id' => $division->id,
        ]);

        $this->session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $period->id,
            'recruitment_division_id' => $division->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab',
            'room' => 'A1',
            'is_active' => true,
        ]);
    }

    public function test_check_in_creates_waiting_interview_without_interviewer(): void
    {
        $application = $this->applicationReadyForInterview($this->session, [
            'registration_number' => 'OPREC-2026-WR001',
        ]);

        $this->checkInApplicant($this->session, $application);

        $interview = RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->first();

        $this->assertNotNull($interview);
        $this->assertSame(InterviewStatus::Waiting, $interview->status);
        $this->assertNull($interview->interviewer_id);
    }

    public function test_interviewer_can_book_and_evaluate(): void
    {
        $application = $this->applicationReadyForInterview($this->session, [
            'registration_number' => 'OPREC-2026-WR002',
        ]);

        $this->checkInApplicant($this->session, $application);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.book', $application))
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(InterviewStatus::InProgress, $application->interview?->status);
        $this->assertSame($this->interviewer->id, $application->interview?->interviewer_id);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), [
                'speaking_score' => 8,
                'technical_score' => 8,
                'attitude_score' => 8,
                'recommendation' => EvaluationRecommendation::Recommended->value,
                'notes' => 'Ok',
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(ApplicationStage::FinalReview, $application->stage);
        $this->assertSame(InterviewStatus::Completed, $application->interview?->status);
    }

    public function test_interviewer_can_release_booking(): void
    {
        $application = $this->applicationReadyForInterview($this->session, [
            'registration_number' => 'OPREC-2026-WR003',
        ]);

        $this->checkInApplicant($this->session, $application);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.book', $application))
            ->assertRedirect();

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.release', $application))
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(InterviewStatus::Waiting, $application->interview?->status);
        $this->assertNull($application->interview?->interviewer_id);
    }
}

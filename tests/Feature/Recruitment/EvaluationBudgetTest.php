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

class EvaluationBudgetTest extends TestCase
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

    public function test_third_save_succeeds_fourth_rejected(): void
    {
        $application = $this->bookedApplication('B1');

        for ($i = 1; $i <= 3; $i++) {
            $payload = $this->payload();
            $payload['speaking_score'] = 5 + $i;

            $this->actingAs($this->interviewer)
                ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $payload)
                ->assertRedirect(route('dashboard.recruitment.my-interviews.show', $application->primaryInterview));
        }

        $evaluation = RecruitmentEvaluation::query()
            ->where('recruitment_application_id', $application->id)
            ->firstOrFail();

        $this->assertSame(3, $evaluation->save_count);
        $this->assertNotNull($evaluation->locked_at);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $this->payload())
            ->assertForbidden();

        $this->assertSame(3, $evaluation->fresh()->save_count);
    }

    public function test_staff_override_blocked_at_cap(): void
    {
        $application = $this->bookedApplication('B2');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $this->payload())
            ->assertRedirect();

        // Override staff ikut memakan budget: 2 override → cap 3.
        foreach ([9, 10] as $score) {
            $override = $this->payload();
            $override['technical_score'] = $score;

            $this->actingAs($this->staff)
                ->post(route('dashboard.recruitment.interviews.evaluation.override', $application->primaryInterview), $override)
                ->assertRedirect();
        }

        $this->assertSame(3, RecruitmentEvaluation::query()
            ->where('recruitment_application_id', $application->id)
            ->value('save_count'));

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interviews.evaluation.override', $application->primaryInterview), $this->payload())
            ->assertForbidden();

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $this->payload())
            ->assertForbidden();
    }

    public function test_save_count_increments(): void
    {
        $application = $this->bookedApplication('B3');

        foreach ([1, 2, 3] as $expected) {
            $payload = $this->payload();
            $payload['attitude_score'] = 4 + $expected;

            $this->actingAs($this->interviewer)
                ->post(route('dashboard.recruitment.my-interviews.evaluate', $application->primaryInterview), $payload)
                ->assertRedirect();

            $this->assertSame($expected, RecruitmentEvaluation::query()
                ->where('recruitment_application_id', $application->id)
                ->value('save_count'));
        }
    }

    private function bookedApplication(string $suffix): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-BG'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->checkInApplicant($this->session, $application, $this->staff);

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->update([
                'interviewer_id' => $this->interviewer->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
                'scheduled_at' => now()->subHour(),
            ]);

        return $application->fresh(['primaryInterview']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'speaking_score' => 8,
            'technical_score' => 7,
            'attitude_score' => 9,
            'recommendation' => EvaluationRecommendation::Recommended->value,
            'notes' => 'Komunikatif dan menguasai dasar divisi.',
        ];
    }
}

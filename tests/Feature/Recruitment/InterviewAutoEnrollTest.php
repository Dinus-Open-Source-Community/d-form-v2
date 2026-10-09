<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\InterviewAutoEnrollService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RecruitmentInterviewFlow;
use Tests\TestCase;

class InterviewAutoEnrollTest extends TestCase
{
    use RefreshDatabase;
    use RecruitmentInterviewFlow;

    private User $staff;

    private User $interviewer;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentDivision $data;

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
        $this->data = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create([
            'interview_starts_at' => today()->subDay()->toDateString(),
            'interview_ends_at' => today()->addMonth()->toDateString(),
        ]);

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->interviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);
    }

    public function test_pass_enrolls_to_nearest_session_and_dispatches_schedule(): void
    {
        $today = $this->makeSession($this->programming->id, today()->toDateString());
        $tomorrow = $this->makeSession($this->programming->id, today()->addDay()->toDateString());

        $application = $this->submittedApplication('E1');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.pass', $application))
            ->assertRedirect();

        $interview = RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->where('interview_kind', RecruitmentInterview::KIND_PRIMARY)
            ->firstOrFail();

        // Sesi terdekat (hari ini) yang dipilih, tanpa interviewer.
        $this->assertSame($today->id, $interview->recruitment_interview_session_id);
        $this->assertNull($interview->interviewer_id);
        $this->assertSame(InterviewStatus::Waiting, $interview->status);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job) use ($application): bool {
            return $job->applicationId === $application->id
                && $job->templateKey === 'passed_screening';
        });

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job) use ($application, $interview): bool {
            return $job->applicationId === $application->id
                && $job->templateKey === 'interview_scheduled'
                && $job->interviewId === $interview->id;
        });
    }

    public function test_pass_without_session_dispatches_only_passed_screening(): void
    {
        $application = $this->submittedApplication('E2', $this->data->id);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.pass', $application))
            ->assertRedirect();

        $this->assertSame(0, RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->count());

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job) use ($application): bool {
            return $job->applicationId === $application->id
                && $job->templateKey === 'passed_screening';
        });

        Queue::assertNotPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job) use ($application): bool {
            return $job->applicationId === $application->id
                && $job->templateKey === 'interview_scheduled';
        });
    }

    public function test_enroll_is_idempotent(): void
    {
        $this->makeSession($this->programming->id, today()->toDateString());

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-E3',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $service = app(InterviewAutoEnrollService::class);

        $first = $service->enroll($application);
        $second = $service->enroll($application->fresh());

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(1, RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->count());
    }

    public function test_session_create_backfills_waiting_interviews(): void
    {
        $bareOne = $this->interviewStageApplication('E4');
        $bareTwo = $this->interviewStageApplication('E5');
        $alreadyHas = $this->interviewStageApplication('E6');
        $otherDivision = $this->interviewStageApplication('E7', $this->data->id);

        RecruitmentInterview::query()->create([
            'recruitment_application_id' => $alreadyHas->id,
            'recruitment_interview_session_id' => $this->makeSession($this->programming->id, today()->toDateString())->id,
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'interviewer_id' => null,
            'scheduled_at' => now(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => InterviewStatus::Waiting,
        ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interview-sessions.store'), [
                'recruitment_period_id' => $this->period->id,
                'recruitment_division_id' => $this->programming->id,
                'session_date' => today()->addDay()->toDateString(),
                'starts_at' => '13:00',
                'ends_at' => '15:00',
                'location' => 'Lab DOSCOM',
                'room' => 'A102',
            ])
            ->assertRedirect();

        $session = RecruitmentInterviewSession::query()
            ->where('recruitment_period_id', $this->period->id)
            ->where('recruitment_division_id', $this->programming->id)
            ->where('room', 'A102')
            ->firstOrFail();

        foreach ([$bareOne, $bareTwo] as $application) {
            $this->assertDatabaseHas('recruitment_interviews', [
                'recruitment_application_id' => $application->id,
                'recruitment_interview_session_id' => $session->id,
                'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
                'status' => InterviewStatus::Waiting->value,
            ]);
        }

        // Sudah punya interview: tidak digandakan. Divisi lain: tidak tersentuh.
        $this->assertSame(1, RecruitmentInterview::query()->where('recruitment_application_id', $alreadyHas->id)->count());
        $this->assertSame(0, RecruitmentInterview::query()->where('recruitment_application_id', $otherDivision->id)->count());
    }

    public function test_pool_excludes_unchecked_in_interviews(): void
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-E8',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $session = $this->makeSession($this->programming->id, today()->toDateString());

        RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $session->id,
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'interviewer_id' => null,
            'scheduled_at' => now()->subMinute(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => InterviewStatus::Waiting,
        ]);

        // Tanpa regis ulang: tidak masuk pool antrean.
        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('primary_opportunities', 0)
                ->where('tab_counts.waiting', 0));
    }

    private function submittedApplication(string $suffix, ?string $divisionId = null): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $divisionId ?? $this->programming->id,
            'registration_number' => 'OPREC-2026-'.$suffix,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'revision_required' => false,
        ]);
    }

    private function interviewStageApplication(string $suffix, ?string $divisionId = null): RecruitmentApplication
    {
        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $divisionId ?? $this->programming->id,
            'registration_number' => 'OPREC-2026-'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);
    }

    private function makeSession(string $divisionId, string $date): RecruitmentInterviewSession
    {
        return RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $divisionId,
            'session_date' => $date,
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }
}

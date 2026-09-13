<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Jobs\Recruitment\SendRecruitmentInterviewerNotificationJob;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\InterviewReminderService;
use App\Services\Recruitment\InterviewSchedulingService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RecruitmentEmailTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecruitmentInterviewSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $interviewer;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentInterviewSession $session;

    private RecruitmentApplication $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        $this->seed(RecruitmentEmailTemplateSeeder::class);

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
            'session_date' => now()->addWeek()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);

        $this->application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);
    }

    public function test_schedule_passed_applicant_creates_interview_and_queues_email(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interview-sessions.schedule', $this->session), [
                'application_ids' => [$this->application->id],
            ])
            ->assertRedirect(route('dashboard.recruitment.interview-sessions.show', $this->session));

        $this->assertDatabaseHas('recruitment_interviews', [
            'recruitment_application_id' => $this->application->id,
            'recruitment_interview_session_id' => $this->session->id,
            'status' => InterviewStatus::Scheduled->value,
        ]);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job): bool {
            return $job->applicationId === $this->application->id
                && $job->templateKey === 'interview_scheduled';
        });

        Queue::assertPushed(SendRecruitmentInterviewerNotificationJob::class);
    }

    public function test_interviewer_assigned_by_primary_division(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interview-sessions.schedule', $this->session), [
                'application_ids' => [$this->application->id],
            ]);

        $interview = RecruitmentInterview::query()->where('recruitment_application_id', $this->application->id)->firstOrFail();
        $this->assertSame($this->interviewer->id, $interview->interviewer_id);
    }

    public function test_staff_reschedule_interview_updates_schedule_and_queues_email(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interview-sessions.schedule', $this->session), [
                'application_ids' => [$this->application->id],
            ]);

        $interview = RecruitmentInterview::query()->where('recruitment_application_id', $this->application->id)->firstOrFail();

        $newSession = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->programming->id,
            'session_date' => now()->addWeeks(2)->toDateString(),
            'starts_at' => '13:00:00',
            'ends_at' => '16:00:00',
            'location' => 'Gedung B',
            'room' => 'B202',
            'is_active' => true,
        ]);

        Queue::fake();

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interviews.reschedule', $interview), [
                'recruitment_interview_session_id' => $newSession->id,
            ])
            ->assertRedirect(route('dashboard.recruitment.interview-sessions.show', $newSession));

        $interview->refresh();
        $this->assertSame($newSession->id, $interview->recruitment_interview_session_id);
        $this->assertSame('Gedung B', $interview->location);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job): bool {
            return $job->templateKey === 'interview_rescheduled';
        });
    }

    public function test_staff_reassign_interviewer_updates_assignment_and_queues_email(): void
    {
        $otherInterviewer = User::factory()->create();
        $otherInterviewer->assignRole('recruitment-interviewer');

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $otherInterviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interview-sessions.schedule', $this->session), [
                'application_ids' => [$this->application->id],
            ]);

        $interview = RecruitmentInterview::query()->where('recruitment_application_id', $this->application->id)->firstOrFail();

        Queue::fake();

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.interviews.reassign', $interview), [
                'interviewer_id' => $otherInterviewer->id,
            ])
            ->assertRedirect(route('dashboard.recruitment.interview-sessions.show', $this->session));

        $interview->refresh();
        $this->assertSame($otherInterviewer->id, $interview->interviewer_id);

        Queue::assertPushed(SendRecruitmentInterviewerNotificationJob::class);
    }

    public function test_h1_reminder_sent_once_within_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));

        $interview = $this->createScheduledInterview(now()->addHours(24));
        $service = app(InterviewReminderService::class);

        Queue::fake();

        $sent = $service->sendDueReminders();
        $this->assertSame(1, $sent);

        $interview->refresh();
        $this->assertNotNull($interview->reminder_h1_sent_at);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job): bool {
            return $job->templateKey === 'interview_reminder_h1';
        });

        Queue::fake();
        $sentAgain = $service->sendDueReminders();
        $this->assertSame(0, $sentAgain);
        Queue::assertNothingPushed();

        Carbon::setTestNow();
    }

    public function test_h2_reminder_sent_within_two_hour_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));

        $interview = $this->createScheduledInterview(now()->addHours(2));
        Queue::fake();

        $sent = app(InterviewReminderService::class)->sendDueReminders();
        $this->assertSame(1, $sent);

        $interview->refresh();
        $this->assertNotNull($interview->reminder_h2_sent_at);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job): bool {
            return $job->templateKey === 'interview_reminder_h2';
        });

        Carbon::setTestNow();
    }

    public function test_no_reminder_for_cancelled_interview(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));

        $interview = $this->createScheduledInterview(now()->addHours(24));
        $interview->update(['status' => InterviewStatus::Cancelled]);

        Queue::fake();

        $sent = app(InterviewReminderService::class)->sendDueReminders();
        $this->assertSame(0, $sent);
        Queue::assertNothingPushed();

        Carbon::setTestNow();
    }

    public function test_artisan_reminder_command_runs_successfully(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));
        $this->createScheduledInterview(now()->addHours(24));

        $this->artisan('recruitment:send-interview-reminders')
            ->assertSuccessful();

        Carbon::setTestNow();
    }

    private function createScheduledInterview(Carbon $scheduledAt): RecruitmentInterview
    {
        app(InterviewSchedulingService::class)->scheduleApplication(
            $this->staff,
            $this->session,
            $this->application,
        );

        $interview = RecruitmentInterview::query()->where('recruitment_application_id', $this->application->id)->firstOrFail();
        $interview->update(['scheduled_at' => $scheduledAt]);

        return $interview->fresh();
    }
}

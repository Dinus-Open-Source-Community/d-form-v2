<?php

namespace Tests\Feature\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\CorrectionRequestStatus;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\InterviewSchedulingService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecruitmentEmailResendTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private RecruitmentApplication $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();

        $this->application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
        ]);
    }

    public function test_resend_tracking_by_owner_returns_redirect_and_logs(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.resend-email', $this->application), [
                'type' => 'tracking',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_activity_logs', [
            'recruitment_application_id' => $this->application->id,
            'action' => 'tracking.resend',
        ]);
    }

    public function test_resend_confirmation_by_owner_returns_redirect_and_logs(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.resend-email', $this->application), [
                'type' => 'confirmation',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_activity_logs', [
            'recruitment_application_id' => $this->application->id,
            'action' => 'email.resend',
        ]);
    }

    public function test_resend_correction_by_owner_returns_redirect_and_logs(): void
    {
        RecruitmentCorrectionRequest::query()->create([
            'recruitment_application_id' => $this->application->id,
            'status' => CorrectionRequestStatus::Pending,
            'request_message' => 'Perbaiki CV.',
        ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.resend-email', $this->application), [
                'type' => 'correction',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_activity_logs', [
            'recruitment_application_id' => $this->application->id,
            'action' => 'email.resend',
        ]);
    }

    public function test_resend_interviewer_by_owner_returns_redirect_and_logs(): void
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        $this->application->update(['stage' => ApplicationStage::Interview]);

        $session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->application->recruitment_period_id,
            'recruitment_division_id' => $division->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);

        app(InterviewSchedulingService::class)->scheduleApplicants(
            $this->staff,
            $session,
            [$this->application->id],
        );

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $this->application->id)
            ->update(['interviewer_id' => $interviewer->id]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.resend-email', $this->application), [
                'type' => 'interviewer',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_activity_logs', [
            'recruitment_application_id' => $this->application->id,
            'action' => 'email.resend',
        ]);
    }

    public function test_resend_notification_replays_last_sent_template(): void
    {
        EmailLog::query()->create([
            'recruitment_application_id' => $this->application->id,
            'recipient_email' => $this->application->personal_email,
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::RecruitmentPassedScreening,
            'sent_at' => now(),
        ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.resend-email', $this->application), [
                'type' => 'notification',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_activity_logs', [
            'recruitment_application_id' => $this->application->id,
            'action' => 'email.resend',
        ]);
    }

    public function test_resend_by_non_owner_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)
            ->post(route('dashboard.recruitment.applications.resend-email', $this->application), [
                'type' => 'tracking',
            ])
            ->assertForbidden();
    }

    public function test_resend_unknown_application_returns_not_found(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.resend-email', Str::uuid()->toString()), [
                'type' => 'tracking',
            ])
            ->assertNotFound();
    }

    public function test_rapid_resend_beyond_daily_limit_is_throttled(): void
    {
        $this->actingAs($this->staff);

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('dashboard.recruitment.applications.resend-email', $this->application), [
                'type' => 'confirmation',
            ])->assertRedirect();
        }

        $this->post(route('dashboard.recruitment.applications.resend-email', $this->application), [
            'type' => 'confirmation',
        ])->assertStatus(429);
    }
}

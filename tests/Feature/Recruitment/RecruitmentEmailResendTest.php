<?php

namespace Tests\Feature\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\CorrectionRequestStatus;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\AttendanceService;
use App\Enums\Recruitment\AttendanceMethod;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

        app(AttendanceService::class)->checkIn(
            $session,
            $this->application,
            AttendanceMethod::RegistrationNumber,
            $this->staff,
        );

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $this->application->id)
            ->update([
                'interviewer_id' => $interviewer->id,
                'status' => \App\Enums\Recruitment\InterviewStatus::InProgress,
                'booked_at' => now(),
            ]);

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

    public function test_resend_notification_replay_includes_whatsapp_block_when_period_has_link(): void
    {
        Mail::fake();

        $this->application->period()->update([
            'whatsapp_group_url' => 'https://chat.whatsapp.com/ReplayLinkWa1234567890',
        ]);

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

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, function (object $mail): bool {
            return str_contains($mail->bodyHtml, 'https://chat.whatsapp.com/ReplayLinkWa1234567890')
                && str_contains($mail->bodyHtml, 'Gabung Grup WA');
        });
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

    public function test_applicant_detail_includes_email_resend_status_and_prereq(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
        ]);

        RecruitmentCorrectionRequest::query()->create([
            'recruitment_application_id' => $application->id,
            'status' => CorrectionRequestStatus::Pending,
            'request_message' => 'Perbaiki CV.',
        ]);

        EmailLog::query()->create([
            'recruitment_application_id' => $application->id,
            'recipient_email' => $application->personal_email,
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::RecruitmentPassedScreening,
            'sent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.applications.resend-email', $application), [
                'type' => 'confirmation',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $period->id,
                'tab' => 'peserta',
                'application' => $application->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applicant_detail.email_resend_status.confirmation.count_24h', 1)
                ->where('applicant_detail.email_resend_status.confirmation.last_attempt_at', fn (?string $value): bool => filled($value))
                ->where('applicant_detail.email_resend_status.tracking.count_24h', 0)
                ->where('applicant_detail.email_resend_status.tracking.last_attempt_at', null)
                ->where('applicant_detail.prereq.has_correction', true)
                ->where('applicant_detail.prereq.has_interview', false)
                ->where('applicant_detail.prereq.has_replayable_notification', true));
    }
}

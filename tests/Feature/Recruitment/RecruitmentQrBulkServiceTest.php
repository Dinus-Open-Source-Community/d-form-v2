<?php

namespace Tests\Feature\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\AttendanceMethod;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentQrBulkService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecruitmentQrBulkServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private RecruitmentPeriod $period;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->period = RecruitmentPeriod::factory()->create([
            'created_by' => $this->staff->id,
        ]);

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $this->session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $division->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    public function test_bulk_qr_dispatches_only_to_interview_stage_without_attendance(): void
    {
        Mail::fake();

        $eligibleOne = $this->makeApplicant(['stage' => ApplicationStage::Interview]);
        $eligibleTwo = $this->makeApplicant(['stage' => ApplicationStage::Interview]);
        $checkedIn = $this->makeApplicant(['stage' => ApplicationStage::Interview]);
        $this->markCheckedIn($checkedIn);
        $screening = $this->makeApplicant(['stage' => ApplicationStage::Screening]);
        $submitted = $this->makeApplicant(['stage' => ApplicationStage::Submitted]);
        $cancelled = $this->makeApplicant(['stage' => ApplicationStage::Interview, 'cancelled_at' => now()]);
        $otherPeriod = RecruitmentPeriod::factory()->create(['created_by' => $this->staff->id]);
        $foreign = $this->makeApplicantFor($otherPeriod, ['stage' => ApplicationStage::Interview]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.send-qr', $this->period))
            ->assertRedirect()
            ->assertSessionHas('message');

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 2);

        foreach ([$eligibleOne, $eligibleTwo] as $application) {
            $this->assertDatabaseHas('email_logs', [
                'recruitment_application_id' => $application->id,
                'notification_type' => 'recruitment_interview_scheduled',
                'status' => 'sent',
            ]);
            $this->assertDatabaseHas('recruitment_activity_logs', [
                'recruitment_application_id' => $application->id,
                'action' => 'email.qr_bulk',
            ]);
        }

        foreach ([$checkedIn, $screening, $submitted, $cancelled, $foreign] as $application) {
            $this->assertDatabaseMissing('email_logs', [
                'recruitment_application_id' => $application->id,
                'notification_type' => 'recruitment_interview_scheduled',
            ]);
        }
    }

    public function test_bulk_qr_with_zero_eligible_returns_ok_zero(): void
    {
        Mail::fake();

        $this->makeApplicant(['stage' => ApplicationStage::Submitted]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.send-qr', $this->period))
            ->assertRedirect()
            ->assertSessionHas('message');

        Mail::assertNothingSent();
    }

    public function test_bulk_qr_by_non_owner_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        // Member tanpa recruitment.dashboard.view ditolak di middleware
        // EnsureRecruitmentAccess via redirect ke dashboard (bukan 403 policy).
        $this->actingAs($member)
            ->post(route('dashboard.recruitment.periods.send-qr', $this->period))
            ->assertRedirect(route('dashboard'));
    }

    public function test_bulk_qr_email_lists_division_sessions_without_empty_shell(): void
    {
        Mail::fake();

        $this->makeApplicant(['stage' => ApplicationStage::Interview]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.send-qr', $this->period))
            ->assertRedirect();

        $expectedDate = today()->translatedFormat('d F Y');

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);
        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, function (object $mail) use ($expectedDate): bool {
            return str_contains($mail->bodyHtml, 'sesi interview')
                && str_contains($mail->bodyHtml, 'Lab DOSCOM')
                && str_contains($mail->bodyHtml, 'A101')
                && str_contains($mail->bodyHtml, $expectedDate)
                && str_contains($mail->bodyHtml, '09:00-12:00');
        });
        Mail::assertNotSent(RecruitmentApplicationConfirmationMail::class, function (object $mail): bool {
            return str_contains($mail->bodyHtml, 'dijadwalkan pada');
        });
    }

    public function test_bulk_qr_email_without_sessions_omits_schedule_card(): void
    {
        Mail::fake();

        $humas = RecruitmentDivision::query()->where('code', 'humas')->firstOrFail();
        $this->makeApplicant([
            'stage' => ApplicationStage::Interview,
            'primary_division_id' => $humas->id,
        ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.send-qr', $this->period))
            ->assertRedirect();

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);
        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, function (object $mail): bool {
            return str_contains($mail->bodyHtml, 'Tunjukkan QR code');
        });
        Mail::assertNotSent(RecruitmentApplicationConfirmationMail::class, function (object $mail): bool {
            return str_contains($mail->bodyHtml, 'Jadwal interview');
        });
    }

    public function test_bulk_qr_dispatch_creates_queued_rows(): void
    {
        Queue::fake();

        $eligible = $this->makeApplicant(['stage' => ApplicationStage::Interview]);

        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        self::assertSame(['dispatched' => 1, 'skipped' => 0], $result);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, 1);

        $this->assertDatabaseHas('email_logs', [
            'recruitment_application_id' => $eligible->id,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled->value,
            'status' => EmailLogStatus::Queued->value,
            'sent_at' => null,
        ]);
        $this->assertDatabaseCount('email_logs', 1);
    }

    public function test_bulk_qr_job_flips_queued_row_to_sent(): void
    {
        Mail::fake();

        $application = $this->makeApplicant(['stage' => ApplicationStage::Interview]);

        EmailLog::query()->create([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $application->personal_email,
            'status' => EmailLogStatus::Queued,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => null,
        ]);

        SendRecruitmentNotificationJob::dispatchSync($application->id, 'interview_scheduled');

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);

        $this->assertDatabaseCount('email_logs', 1);
        $this->assertDatabaseHas('email_logs', [
            'recruitment_application_id' => $application->id,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled->value,
            'status' => EmailLogStatus::Sent->value,
        ]);
        self::assertNotNull(
            EmailLog::query()
                ->where('recruitment_application_id', $application->id)
                ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled->value)
                ->firstOrFail()->sent_at
        );
    }

    public function test_bulk_qr_reclick_skips_already_sent(): void
    {
        Mail::fake();

        $alreadySent = $this->makeApplicant(['stage' => ApplicationStage::Interview]);
        $fresh = $this->makeApplicant(['stage' => ApplicationStage::Interview]);

        EmailLog::query()->create([
            'recruitment_application_id' => $alreadySent->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $alreadySent->personal_email,
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => now(),
        ]);

        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        self::assertSame(['dispatched' => 1, 'skipped' => 1], $result);

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);

        self::assertSame(1, EmailLog::query()
            ->where('recruitment_application_id', $alreadySent->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled->value)
            ->count());
        $this->assertDatabaseHas('email_logs', [
            'recruitment_application_id' => $fresh->id,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled->value,
            'status' => EmailLogStatus::Sent->value,
        ]);
    }

    public function test_bulk_qr_status_endpoint_aggregates_latest_log_per_application(): void
    {
        $sent = $this->makeApplicant(['stage' => ApplicationStage::Interview]);
        $failed = $this->makeApplicant(['stage' => ApplicationStage::Interview]);
        $queued = $this->makeApplicant(['stage' => ApplicationStage::Interview]);
        $silent = $this->makeApplicant(['stage' => ApplicationStage::Interview]);

        foreach ([
            [$sent, EmailLogStatus::Sent, null, now()],
            [$failed, EmailLogStatus::Failed, 'smtp down', null],
            [$queued, EmailLogStatus::Queued, null, null],
        ] as [$application, $status, $error, $sentAt]) {
            EmailLog::query()->create([
                'recruitment_application_id' => $application->id,
                'event_id' => null,
                'user_id' => null,
                'recipient_email' => (string) $application->personal_email,
                'status' => $status,
                'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
                'error_message' => $error,
                'sent_at' => $sentAt,
            ]);
        }

        $response = $this->actingAs($this->staff)
            ->getJson(route('dashboard.recruitment.periods.qr-status', $this->period))
            ->assertOk();

        $response->assertJson([
            'sent' => 1,
            'failed' => 1,
            'queued' => 1,
        ]);

        $recipients = $response->json('recipients');
        self::assertCount(3, $recipients);
        self::assertSame(
            [$sent->id, $failed->id, $queued->id],
            array_column($recipients, 'application_id')
        );

        foreach ($recipients as $recipient) {
            self::assertSame(
                ['application_id', 'full_name', 'registration_number', 'status', 'error', 'sent_at'],
                array_keys($recipient)
            );
        }

        $byId = array_column($recipients, null, 'application_id');
        self::assertSame('sent', $byId[$sent->id]['status']);
        self::assertSame('failed', $byId[$failed->id]['status']);
        self::assertSame('smtp down', $byId[$failed->id]['error']);
        self::assertSame('queued', $byId[$queued->id]['status']);
        self::assertNull($byId[$queued->id]['sent_at']);
        self::assertArrayNotHasKey($silent->id, $byId);
    }

    public function test_bulk_qr_include_sent_resends_with_fresh_queued_row(): void
    {
        Mail::fake();

        $alreadySent = $this->makeApplicant(['stage' => ApplicationStage::Interview]);

        EmailLog::query()->create([
            'recruitment_application_id' => $alreadySent->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $alreadySent->personal_email,
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => now()->subHour(),
        ]);

        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period, true);

        self::assertSame(['dispatched' => 1, 'skipped' => 0], $result);

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);

        // Riwayat dipertahankan: baris Sent lama + baris baru (job flip Queued->Sent).
        self::assertSame(2, EmailLog::query()
            ->where('recruitment_application_id', $alreadySent->id)
            ->where('notification_type', EmailNotificationType::RecruitmentInterviewScheduled->value)
            ->where('status', EmailLogStatus::Sent->value)
            ->count());
    }

    public function test_bulk_qr_include_sent_creates_fresh_queued_row_before_job_runs(): void
    {
        Queue::fake();

        $alreadySent = $this->makeApplicant(['stage' => ApplicationStage::Interview]);

        EmailLog::query()->create([
            'recruitment_application_id' => $alreadySent->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $alreadySent->personal_email,
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => now()->subHour(),
        ]);

        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period, true);

        self::assertSame(['dispatched' => 1, 'skipped' => 0], $result);

        $this->assertDatabaseHas('email_logs', [
            'recruitment_application_id' => $alreadySent->id,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled->value,
            'status' => EmailLogStatus::Sent->value,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'recruitment_application_id' => $alreadySent->id,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled->value,
            'status' => EmailLogStatus::Queued->value,
            'sent_at' => null,
        ]);
    }

    public function test_bulk_qr_send_route_accepts_include_sent_flag(): void
    {
        Mail::fake();

        $alreadySent = $this->makeApplicant(['stage' => ApplicationStage::Interview]);

        EmailLog::query()->create([
            'recruitment_application_id' => $alreadySent->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $alreadySent->personal_email,
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => now()->subHour(),
        ]);

        // Default: lewati yang sudah terkirim.
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.send-qr', $this->period))
            ->assertRedirect();

        Mail::assertNothingSent();

        // include_sent=true: kirim ulang.
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.send-qr', $this->period), ['include_sent' => true])
            ->assertRedirect();

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);
    }

    public function test_bulk_qr_skipped_count_matches_resendable_pool(): void
    {
        Queue::fake();

        $sent = $this->makeApplicant(['stage' => ApplicationStage::Interview]);
        $this->makeApplicant(['stage' => ApplicationStage::Interview]);
        $checkedIn = $this->makeApplicant(['stage' => ApplicationStage::Interview]);
        $this->markCheckedIn($checkedIn);

        foreach ([$sent, $checkedIn] as $application) {
            EmailLog::query()->create([
                'recruitment_application_id' => $application->id,
                'event_id' => null,
                'user_id' => null,
                'recipient_email' => (string) $application->personal_email,
                'status' => EmailLogStatus::Sent,
                'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
                'error_message' => null,
                'sent_at' => now(),
            ]);
        }

        // checkedIn tidak masuk pool (sudah absen) → skipped hanya $sent, dispatched hanya yang fresh.
        $result = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period);

        self::assertSame(['dispatched' => 1, 'skipped' => 1], $result);

        $resend = app(RecruitmentQrBulkService::class)->send($this->staff, $this->period, true);

        self::assertSame(['dispatched' => 2, 'skipped' => 0], $resend);
    }

    /** @param array<string, mixed> $overrides */
    private function makeApplicant(array $overrides = []): RecruitmentApplication
    {
        return $this->makeApplicantFor($this->period, $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function makeApplicantFor(RecruitmentPeriod $period, array $overrides = []): RecruitmentApplication
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        return RecruitmentApplication::factory()->create(array_merge([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
        ], $overrides));
    }

    private function markCheckedIn(RecruitmentApplication $application): void
    {
        RecruitmentAttendance::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $this->session->id,
            'method' => AttendanceMethod::RegistrationNumber,
            'checked_in_at' => now(),
            'checked_in_by' => $this->staff->id,
        ]);
    }
}

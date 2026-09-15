<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\AttendanceMethod;
use App\Enums\Recruitment\InterviewStatus;
use App\Enums\Recruitment\QueueStatus;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentQueueEntry;
use App\Models\User;
use App\Services\Recruitment\InterviewSchedulingService;
use App\Services\Recruitment\QueueService;
use App\Services\Recruitment\AttendanceService;
use App\Support\RecruitmentQrPayload;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\TestCase;

class RecruitmentAttendanceQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

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

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

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

    private function scheduleApplicant(string $suffix = '1'): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-0000'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $interviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

        app(InterviewSchedulingService::class)->scheduleApplicants(
            $this->staff,
            $this->session,
            [$application->id],
        );

        return $application->fresh(['interview']);
    }

    private function staffCheckInByRegistrationNumber(RecruitmentApplication $application): void
    {
        $this->actingAs($this->staff)
            ->postJson(route('dashboard.scan.store'), [
                'raw' => $application->registration_number,
            ])
            ->assertOk();
    }

    public function test_public_self_check_in_route_is_not_available(): void
    {
        $application = $this->scheduleApplicant('1');

        $this->postJson('/open-recruitment/attendance/check-in', [
            'registration_number' => $application->registration_number,
        ])->assertNotFound();
    }

    public function test_staff_check_in_via_registration_number_records_attendance(): void
    {
        $application = $this->scheduleApplicant('1');

        $this->actingAs($this->staff)
            ->postJson(route('dashboard.scan.store'), [
                'raw' => $application->registration_number,
            ])
            ->assertOk()
            ->assertJsonPath('attendee.queue_number', 1);

        $this->assertDatabaseHas('recruitment_attendances', [
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $this->session->id,
            // Global scan transports manual codes and QR alike as `raw`, so the method resolves to qr.
            'method' => AttendanceMethod::Qr->value,
        ]);

        $this->assertDatabaseHas('recruitment_queue_entries', [
            'recruitment_application_id' => $application->id,
            'queue_number' => 1,
            'status' => QueueStatus::Waiting->value,
        ]);

        $this->assertDatabaseHas('recruitment_interviews', [
            'recruitment_application_id' => $application->id,
            'status' => InterviewStatus::Queued->value,
        ]);
    }

    public function test_check_in_via_qr_valid_payload_records_attendance(): void
    {
        $application = $this->scheduleApplicant('2');
        $payload = RecruitmentQrPayload::encode($application->id);

        $this->actingAs($this->staff)
            ->postJson(route('dashboard.scan.store'), [
                'raw' => $payload,
            ])
            ->assertOk()
            ->assertJsonPath('attendee.application_id', $application->id);

        $this->assertDatabaseHas('recruitment_attendances', [
            'recruitment_application_id' => $application->id,
            'method' => AttendanceMethod::Qr->value,
        ]);
    }

    public function test_duplicate_check_in_is_idempotent(): void
    {
        $application = $this->scheduleApplicant('3');

        $this->staffCheckInByRegistrationNumber($application);

        $this->actingAs($this->staff)
            ->postJson(route('dashboard.scan.store'), [
                'raw' => $application->registration_number,
            ])
            ->assertStatus(409);

        $this->assertSame(
            1,
            RecruitmentAttendance::query()->where('recruitment_application_id', $application->id)->count(),
        );
    }

    public function test_queue_fcfs_assigns_lower_number_to_earlier_check_in(): void
    {
        $applicationA = $this->scheduleApplicant('4');
        $applicationB = $this->scheduleApplicant('5');

        Carbon::setTestNow('2026-09-08 09:01:00');
        $this->staffCheckInByRegistrationNumber($applicationA);

        Carbon::setTestNow('2026-09-08 09:04:00');
        $this->staffCheckInByRegistrationNumber($applicationB);

        Carbon::setTestNow();

        $queueA = RecruitmentQueueEntry::query()->where('recruitment_application_id', $applicationA->id)->first();
        $queueB = RecruitmentQueueEntry::query()->where('recruitment_application_id', $applicationB->id)->first();

        $this->assertNotNull($queueA);
        $this->assertNotNull($queueB);
        $this->assertTrue($queueA->queue_number < $queueB->queue_number);
    }

    public function test_late_arrival_is_appended_to_queue_tail(): void
    {
        $applicationA = $this->scheduleApplicant('6');
        $applicationB = $this->scheduleApplicant('7');
        $applicationC = $this->scheduleApplicant('8');

        Carbon::setTestNow('2026-09-08 09:01:00');
        $this->staffCheckInByRegistrationNumber($applicationA);

        Carbon::setTestNow('2026-09-08 09:04:00');
        $this->staffCheckInByRegistrationNumber($applicationB);

        Carbon::setTestNow('2026-09-08 10:30:00');
        $this->staffCheckInByRegistrationNumber($applicationC);

        Carbon::setTestNow();

        $queueC = RecruitmentQueueEntry::query()->where('recruitment_application_id', $applicationC->id)->first();

        $this->assertNotNull($queueC);
        $this->assertSame(3, $queueC->queue_number);

        $maxNumber = (int) RecruitmentQueueEntry::query()
            ->where('recruitment_interview_session_id', $this->session->id)
            ->max('queue_number');

        $this->assertSame(3, $maxNumber);
    }

    public function test_queue_entry_cannot_be_created_without_attendance(): void
    {
        $application = $this->scheduleApplicant('9');

        $attendance = new RecruitmentAttendance([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $this->session->id,
            'method' => AttendanceMethod::RegistrationNumber,
            'checked_in_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        app(QueueService::class)->createFromAttendance($attendance);
    }

    public function test_no_show_path_updates_interview_status(): void
    {
        $application = $this->scheduleApplicant('0');

        $this->actingAs($this->staff)
            ->postJson(route('dashboard.recruitment.queue.no-show', $this->session), [
                'application_id' => $application->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('recruitment_interviews', [
            'recruitment_application_id' => $application->id,
            'status' => InterviewStatus::NoShow->value,
        ]);

        $this->assertDatabaseMissing('recruitment_attendances', [
            'recruitment_application_id' => $application->id,
        ]);
    }

    public function test_mark_no_show_rejects_when_already_checked_in(): void
    {
        $application = $this->scheduleApplicant('1');

        app(AttendanceService::class)->checkInFromInput(
            $this->session,
            $application->registration_number,
            null,
            null,
            $this->staff,
        );

        $this->actingAs($this->staff)
            ->postJson(route('dashboard.recruitment.queue.no-show', $this->session), [
                'application_id' => $application->id,
            ])
            ->assertStatus(422);
    }

    public function test_staff_can_view_queue_monitor(): void
    {
        $this->actingAs($this->staff)
            ->get(route('dashboard.recruitment.queue.show', $this->session))
            ->assertOk();
    }

    public function test_tracking_exposes_attendance_qr_before_check_in(): void
    {
        $trackingToken = 'valid-tracking-token-1234567890ab';

        $application = RecruitmentApplication::factory()
            ->for($this->period, 'period')
            ->withTrackingToken($trackingToken)
            ->create([
                'primary_division_id' => $this->programming->id,
                'registration_number' => 'OPREC-2026-00999',
                'stage' => ApplicationStage::Interview,
                'result' => ApplicationResult::Pending,
            ]);

        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $interviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

        app(InterviewSchedulingService::class)->scheduleApplicants(
            $this->staff,
            $this->session,
            [$application->id],
        );

        $this->post(route('open-recruitment.track.authenticate'), [
            'registration_number' => $application->registration_number,
            'tracking_token' => $trackingToken,
        ])->assertRedirect(route('open-recruitment.track.show'));

        $this->get(route('open-recruitment.track.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpenRecruitment/Track/Show')
                ->where('tracking.attendance', null)
                ->whereNotNull('tracking.attendance_qr_base64'));
    }

    public function test_interview_scheduled_email_includes_embedded_qr(): void
    {
        Mail::fake();

        $application = $this->scheduleApplicant('m');

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, function (RecruitmentApplicationConfirmationMail $mail) use ($application): bool {
            return $mail->hasTo($application->personal_email)
                && $mail->qrPngBinary !== null
                && $mail->qrPngBinary !== '';
        });

        $png = app(\App\Services\Recruitment\RecruitmentQrPngGenerator::class)
            ->pngForApplication($application->id);

        $mail = new RecruitmentApplicationConfirmationMail(
            subjectLine: 'Test',
            bodyHtml: '<p>Test</p>',
            bodyText: 'Test',
            qrPngBinary: $png,
        );

        $html = $mail->render();

        $this->assertStringContainsString('cid:', $html);
        $this->assertStringNotContainsString('data:image/png;base64,', $html);
    }
}

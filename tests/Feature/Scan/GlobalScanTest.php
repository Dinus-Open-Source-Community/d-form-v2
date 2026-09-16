<?php

namespace Tests\Feature\Scan;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Jobs\RecordAttendanceJob;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\InterviewSchedulingService;
use App\Support\RecruitmentQrPayload;
use App\Support\RegistrationQrPayload;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GlobalScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    /**
     * @return array{0: User, 1: RecruitmentApplication}
     */
    private function scheduledRecruitmentApplication(string $suffix): array
    {
        $this->seed(RecruitmentDivisionSeeder::class);

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();

        $session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $period->id,
            'recruitment_division_id' => $programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $programming->id,
            'registration_number' => 'OPREC-2026-0000'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $interviewer->id,
            'recruitment_division_id' => $programming->id,
        ]);

        app(InterviewSchedulingService::class)->scheduleApplicants(
            $staff,
            $session,
            [$application->id],
        );

        return [$staff, $application->fresh()];
    }

    /**
     * @return array{0: Event, 1: Form}
     */
    private function eventWithForm(): array
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        return [$event, $form];
    }

    public function test_admin_event_qr_returns_200_creates_row_and_dispatches_job(): void
    {
        Queue::fake();

        [$event, $form] = $this->eventWithForm();
        $participant = User::factory()->create(['email' => 'global@example.test']);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'CHK-GLB-001',
        ]);

        $this->actingAs($this->admin())->postJson(route('dashboard.scan.store'), [
            'raw' => RegistrationQrPayload::encode($answer->id),
            'desk' => 'meja-1',
        ])->assertOk()->assertJsonPath('type', 'event');

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
        ]);

        Queue::assertPushed(RecordAttendanceJob::class, function (RecordAttendanceJob $job) use ($event, $answer): bool {
            return $job->attendanceId === EventAttendance::query()
                ->where('event_id', $event->id)
                ->where('form_answer_id', $answer->id)
                ->value('id');
        });
    }

    public function test_guest_gets_unauthorized(): void
    {
        $this->postJson(route('dashboard.scan.store'), ['raw' => '{"v":1}'])->assertUnauthorized();
    }

    public function test_member_gets_forbidden(): void
    {
        $this->actingAs($this->member())->postJson(route('dashboard.scan.store'), [
            'raw' => '{"v":1}',
        ])->assertForbidden();
    }

    public function test_unknown_payload_returns_422(): void
    {
        $this->actingAs($this->admin())->postJson(route('dashboard.scan.store'), [
            'raw' => 'bukan-qr-sama-sekali',
        ])->assertUnprocessable();
    }

    public function test_staff_recruitment_qr_returns_200_with_queue_number(): void
    {
        [$staff, $application] = $this->scheduledRecruitmentApplication('R1');

        $this->actingAs($staff)->postJson(route('dashboard.scan.store'), [
            'raw' => RecruitmentQrPayload::encode($application->id),
        ])->assertOk()
            ->assertJsonPath('type', 'recruitment')
            ->assertJsonPath('attendee.queue_number', 1);

        $this->assertDatabaseHas('recruitment_attendances', [
            'recruitment_application_id' => $application->id,
        ]);
    }

    public function test_staff_recruitment_duplicate_returns_409(): void
    {
        [$staff, $application] = $this->scheduledRecruitmentApplication('R2');
        $payload = ['raw' => RecruitmentQrPayload::encode($application->id)];

        $this->actingAs($staff)->postJson(route('dashboard.scan.store'), $payload)->assertOk();

        $this->actingAs($staff)->postJson(route('dashboard.scan.store'), $payload)
            ->assertStatus(409)
            ->assertJsonPath('attendee.queue_number', 1);
    }

    public function test_scan_store_is_rate_limited_per_user(): void
    {
        $admin = $this->admin();

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->actingAs($admin)
                ->postJson(route('dashboard.scan.store'), ['raw' => 'bukan-qr'])
                ->assertUnprocessable();
        }

        $this->actingAs($admin)
            ->postJson(route('dashboard.scan.store'), ['raw' => 'bukan-qr'])
            ->assertStatus(429);
    }

    public function test_over_long_raw_payload_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('dashboard.scan.store'), ['raw' => str_repeat('a', 4097)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('raw');
    }
}

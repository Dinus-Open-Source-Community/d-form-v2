<?php

namespace Tests\Feature\Dashboard\Events;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Jobs\RecordAttendanceJob;
use App\Mail\AttendanceConfirmedMail;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Support\RegistrationQrPayload;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AttendanceScanTest extends TestCase
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

    private function eventWithForm(array $eventOverrides = []): array
    {
        $event = Event::factory()->create(array_merge([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ], $eventOverrides));

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        return [$event, $form];
    }

    public function test_guest_post_returns_unauthorized(): void
    {
        [$event] = $this->eventWithForm();

        $this->postJson(route('dashboard.events.attendance-scan.store', $event), [
            'raw_payload' => '{"v":1}',
        ])->assertUnauthorized();
    }

    public function test_member_cannot_post_attendance_scan(): void
    {
        [$event] = $this->eventWithForm();

        $this->actingAs($this->member())->postJson(route('dashboard.events.attendance-scan.store', $event), [
            'raw_payload' => '{"v":1}',
        ])->assertForbidden();
    }

    public function test_member_cannot_view_scan_page(): void
    {
        [$event] = $this->eventWithForm();

        $this->actingAs($this->member())->get(route('dashboard.events.scan', $event))->assertForbidden();
    }

    public function test_admin_can_view_scan_page(): void
    {
        [$event] = $this->eventWithForm();

        $this->actingAs($this->admin())->get(route('dashboard.events.scan', $event))->assertOk();
    }

    public function test_admin_queues_job_for_valid_qr_payload(): void
    {
        Queue::fake();

        [$event, $form] = $this->eventWithForm();
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'CHK-001',
        ]);

        $payload = RegistrationQrPayload::encode($answer->id);

        $response = $this->actingAs($this->admin())->postJson(route('dashboard.events.attendance-scan.store', $event), [
            'raw_payload' => $payload,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('attendee.name', $participant->name)
            ->assertJsonPath('attendee.email', $participant->email)
            ->assertJsonPath('attendee.form_answer_id', $answer->id);

        Queue::assertPushed(RecordAttendanceJob::class, function (RecordAttendanceJob $job) use ($event, $answer): bool {
            return $job->eventId === $event->id
                && $job->formAnswerId === $answer->id;
        });
    }

    public function test_registration_code_field_is_accepted_case_insensitive(): void
    {
        Queue::fake();

        [$event, $form] = $this->eventWithForm();
        $participant = User::factory()->create(['email' => 'p2@example.test']);
        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'AbC-99',
        ]);

        $this->actingAs($this->admin())->postJson(route('dashboard.events.attendance-scan.store', $event), [
            'registration_code' => 'abc-99',
        ])->assertAccepted();

        Queue::assertPushed(RecordAttendanceJob::class);
    }

    public function test_duplicate_scan_returns_409(): void
    {
        [$event, $form] = $this->eventWithForm();
        $admin = $this->admin();
        $participant = User::factory()->create(['email' => 'dup@example.test']);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'DUP-01',
        ]);

        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $admin->id,
            'scanned_at' => now(),
        ]);

        Queue::fake();

        $this->actingAs($admin)->postJson(route('dashboard.events.attendance-scan.store', $event), [
            'raw_payload' => RegistrationQrPayload::encode($answer->id),
        ])->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_pending_registration_returns_422(): void
    {
        [$event, $form] = $this->eventWithForm();
        $participant = User::factory()->create(['email' => 'pend@example.test']);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $this->actingAs($this->admin())->postJson(route('dashboard.events.attendance-scan.store', $event), [
            'raw_payload' => RegistrationQrPayload::encode($answer->id),
        ])->assertUnprocessable();
    }

    public function test_wrong_event_returns_422(): void
    {
        [$eventA] = $this->eventWithForm();
        [, $formB] = $this->eventWithForm();

        $participant = User::factory()->create(['email' => 'w@example.test']);
        $answerB = FormAnswer::factory()->create([
            'form_id' => $formB->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        $this->actingAs($this->admin())->postJson(route('dashboard.events.attendance-scan.store', $eventA), [
            'raw_payload' => RegistrationQrPayload::encode($answerB->id),
        ])->assertUnprocessable();
    }

    public function test_record_attendance_job_inserts_row_and_sends_mail(): void
    {
        Mail::fake();

        [$event, $form] = $this->eventWithForm();
        $scanner = $this->admin();
        $participant = User::factory()->create(['email' => 'job@example.test']);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        RecordAttendanceJob::dispatchSync($event->id, $answer->id, $scanner->id);

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $scanner->id,
        ]);

        Mail::assertSent(AttendanceConfirmedMail::class);

        $this->assertDatabaseHas('email_logs', [
            'form_answer_id' => $answer->id,
            'event_id' => $event->id,
            'user_id' => $participant->id,
        ]);
    }

    public function test_record_attendance_job_skips_duplicate_mail_when_row_exists(): void
    {
        Mail::fake();

        [$event, $form] = $this->eventWithForm();
        $scanner = $this->admin();
        $participant = User::factory()->create(['email' => 'skip@example.test']);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $scanner->id,
            'scanned_at' => now()->subMinute(),
        ]);

        RecordAttendanceJob::dispatchSync($event->id, $answer->id, $scanner->id);

        Mail::assertNothingSent();
        $this->assertSame(1, EventAttendance::query()->where('event_id', $event->id)->where('form_answer_id', $answer->id)->count());
    }
}

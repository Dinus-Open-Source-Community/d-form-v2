<?php

namespace Tests\Feature\Scan;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormSession;
use App\Models\User;
use App\Support\RegistrationQrPayload;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClassAttendanceScanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    /** @return array{0: Form, 1: FormAnswer} */
    private function submission(string $purpose, array $dates = []): array
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addMonth(),
        ]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'metadata' => ['purpose' => $purpose],
        ]);
        foreach ($dates as $date) {
            FormSession::factory()->create(['form_id' => $form->id, 'session_date' => $date]);
        }
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        return [$form, $answer];
    }

    public function test_scan_is_rejected_outside_a_scheduled_date(): void
    {
        $this->travelTo(Carbon::parse('2026-12-08 10:00:00', 'Asia/Jakarta'));
        [, $answer] = $this->submission('d_kelas', ['2026-12-07']);

        $this->actingAs($this->admin)
            ->postJson(route('dashboard.scan.store'), ['raw' => RegistrationQrPayload::encode($answer->id)])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Hari ini tidak ada jadwal kelas untuk form ini.')
            ->assertJsonStructure(['type', 'eventTitle', 'attendee', 'message']);
    }

    public function test_scan_records_one_attendance_per_scheduled_date(): void
    {
        $this->travelTo(Carbon::parse('2026-12-07 19:00:00', 'Asia/Jakarta'));
        [$form, $answer] = $this->submission('d_kelas', ['2026-12-07', '2026-12-14']);
        $payload = ['raw' => RegistrationQrPayload::encode($answer->id)];

        $this->actingAs($this->admin)->postJson(route('dashboard.scan.store'), $payload)
            ->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('session.session_date', '2026-12-07');
        $sessionId = FormSession::query()->where('form_id', $form->id)->whereDate('session_date', '2026-12-07')->value('id');
        $this->assertDatabaseHas('event_attendances', ['form_answer_id' => $answer->id, 'form_session_id' => $sessionId, 'attendance_slot' => $sessionId]);

        $this->actingAs($this->admin)->postJson(route('dashboard.scan.store'), $payload)->assertStatus(409);
        $this->travelTo(Carbon::parse('2026-12-14 19:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->admin)->postJson(route('dashboard.scan.store'), $payload)->assertOk();
        $this->assertDatabaseCount('event_attendances', 2);
    }

    public function test_answer_collection_and_feedback_forms_reject_scanning(): void
    {
        foreach (['answer_collection', 'feedback'] as $purpose) {
            [, $answer] = $this->submission($purpose);
            $this->actingAs($this->admin)->postJson(route('dashboard.scan.store'), ['raw' => RegistrationQrPayload::encode($answer->id)])
                ->assertStatus(422)->assertJsonPath('message', 'Form ini tidak memakai absensi.')
                ->assertJsonStructure(['type', 'eventTitle', 'attendee', 'message']);
        }
    }

    public function test_legacy_other_form_keeps_single_attendance_behaviour(): void
    {
        [, $answer] = $this->submission('other');
        $payload = ['raw' => RegistrationQrPayload::encode($answer->id)];
        $this->actingAs($this->admin)->postJson(route('dashboard.scan.store'), $payload)->assertOk();
        $this->actingAs($this->admin)->postJson(route('dashboard.scan.store'), $payload)->assertStatus(409);
        $this->assertDatabaseHas('event_attendances', ['form_answer_id' => $answer->id, 'attendance_slot' => 'default', 'form_session_id' => null]);
    }
}

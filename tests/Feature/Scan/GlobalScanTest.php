<?php

namespace Tests\Feature\Scan;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Jobs\RecordAttendanceJob;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Support\RegistrationQrPayload;
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

    public function test_admin_event_qr_returns_202_and_dispatches_job(): void
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
        ])->assertAccepted()->assertJsonPath('type', 'event');

        Queue::assertPushed(RecordAttendanceJob::class);
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
}

<?php

namespace Tests\Feature\Forms;

use App\Enums\FormPurpose;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormSession;
use App\Models\User;
use App\Models\Event;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('admin');
    }

    public function test_d_kelas_purpose_resolves_to_enum(): void
    {
        $form = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']]);

        $this->assertSame(FormPurpose::DKelas, $form->purpose());
    }

    public function test_only_d_kelas_forms_can_store_sessions(): void
    {
        $form = Form::factory()->create(['event_id' => Event::factory()->create(['created_by' => $this->owner->id])->id, 'metadata' => ['purpose' => 'feedback']]);

        $this->actingAs($this->owner)
            ->post(route('dashboard.events.forms.sessions.store', [$form->event, $form]), ['session_date' => '2026-12-07'])
            ->assertForbidden();
    }

    public function test_session_cannot_be_deleted_when_attendance_exists(): void
    {
        $event = Event::factory()->create(['created_by' => $this->owner->id]);
        $this->owner->assignRole('admin');
        $this->owner->assignRole('super-admin');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->assertTrue($this->owner->can('update', $event));
        $form = Form::factory()->create(['event_id' => $event->id, 'metadata' => ['purpose' => 'd_kelas']]);
        $session = FormSession::factory()->create(['form_id' => $form->id]);
        EventAttendance::query()->create([
            'event_id' => $session->form->event_id,
            'form_answer_id' => FormAnswer::factory()->create(['form_id' => $session->form_id])->id,
            'form_session_id' => $session->id,
            'attendance_slot' => $session->id,
            'scanned_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->delete(route('dashboard.events.forms.sessions.destroy', ['event' => $event, 'form' => $form, 'session' => $session]))
            ->assertSessionHasErrors('session');
    }

    public function test_form_rejects_the_101st_session(): void
    {
        $event = Event::factory()->create(['created_by' => $this->owner->id]);
        $this->owner->assignRole('super-admin');
        $form = Form::factory()->count(1)->create(['event_id' => $event->id, 'metadata' => ['purpose' => 'd_kelas']])->firstOrFail();
        FormSession::factory()
            ->count(100)
            ->state(new Sequence(static function (Sequence $sequence): array {
                return ['session_date' => Carbon::create(2099, 1, 1)->addDays($sequence->index)->toDateString()];
            }))
            ->create(['form_id' => $form->id]);

        $this->actingAs($this->owner)
            ->post(route('dashboard.events.forms.sessions.store', ['event' => $event, 'form' => $form]), ['session_date' => '2099-12-31'])
            ->assertSessionHasErrors('session_date');
    }

    public function test_session_date_must_be_unique_per_form(): void
    {
        $event = Event::factory()->create(['created_by' => $this->owner->id]);
        $this->owner->assignRole('admin');
        $this->owner->assignRole('super-admin');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->assertTrue($this->owner->can('update', $event));
        $form = Form::factory()->create(['event_id' => $event->id, 'metadata' => ['purpose' => 'd_kelas']]);
        $session = FormSession::factory()->create(['session_date' => '2026-12-07', 'form_id' => $form->id]);

        $this->actingAs($this->owner)
            ->post(route('dashboard.events.forms.sessions.store', ['event' => $event, 'form' => $form]), ['session_date' => '2026-12-07'])
            ->assertSessionHasErrors('session_date');
    }
}

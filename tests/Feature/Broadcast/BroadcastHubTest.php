<?php

namespace Tests\Feature\Broadcast;

use App\Models\Broadcast;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_b01_create_with_valid_event_id_returns_locked_prefill(): void
    {
        $event = Event::factory()->create();

        $this->actingAs($this->superAdmin())
            ->get(route('dashboard.broadcasts.create', ['event_id' => $event->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('prefill.event_id', $event->id)
                ->where('prefill.locked_event', true)
                ->where('prefill.event_title', $event->title));
    }

    public function test_b02_recruitment_applicants_without_period_id_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson(route('dashboard.broadcasts.store'), [
                'name' => 'Info OpRec',
                'source' => 'recruitment_applicants',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_id')
            ->assertJsonPath('errors.period_id.0', 'Pilih periode dulu — sumber pelamar wajib menyertakan period_id.');
    }

    public function test_b03_event_participants_without_event_id_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson(route('dashboard.broadcasts.store'), [
                'name' => 'Info Peserta',
                'source' => 'event_participants',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_id')
            ->assertJsonPath('errors.event_id.0', 'Pilih event dulu — sumber peserta event wajib menyertakan event_id.');
    }

    public function test_b04_context_admin_can_send_to_own_event_but_not_others(): void
    {
        $contextAdmin = User::factory()->create();
        $contextAdmin->givePermissionTo('events.view');

        $otherAdmin = User::factory()->create();

        $ownEvent = Event::factory()->create(['created_by' => $contextAdmin->id]);
        $otherEvent = Event::factory()->create(['created_by' => $otherAdmin->id]);

        $this->actingAs($contextAdmin)
            ->post(route('dashboard.broadcasts.store'), [
                'name' => 'Info Peserta X',
                'source' => 'event_participants',
                'event_id' => $ownEvent->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('broadcasts', [
            'name' => 'Info Peserta X',
            'event_id' => $ownEvent->id,
        ]);

        $this->actingAs($contextAdmin)
            ->post(route('dashboard.broadcasts.store'), [
                'name' => 'Info Peserta Y',
                'source' => 'event_participants',
                'event_id' => $otherEvent->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('broadcasts', ['name' => 'Info Peserta Y']);
    }

    public function test_b05_snapshot_is_stored_as_source_of_truth(): void
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        $memberA = User::factory()->create(['email' => 'peserta-a@example.com']);
        $memberB = User::factory()->create(['email' => 'peserta-b@example.com']);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $memberA->id]);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $memberB->id]);

        $this->actingAs($this->superAdmin())
            ->post(route('dashboard.broadcasts.store'), [
                'name' => 'Pengumuman Event',
                'source' => 'event_participants',
                'event_id' => $event->id,
            ])
            ->assertRedirect();

        $broadcast = Broadcast::query()->where('name', 'Pengumuman Event')->firstOrFail();

        $this->assertSame(2, $broadcast->recipient_count);

        $snapshot = $broadcast->recipient_snapshot;

        $this->assertSame('event_participants', $snapshot['source']);
        $this->assertSame($event->id, $snapshot['event_id']);
        $this->assertSame(2, $snapshot['total']);
        $this->assertSame(
            ['peserta-a@example.com', 'peserta-b@example.com'],
            array_column($snapshot['recipients'], 'email')
        );

        $this->actingAs($this->superAdmin())
            ->get(route('dashboard.broadcasts.show', $broadcast))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('snapshot.total', 2)
                ->where('broadcast.recipient_count', 2));
    }
}

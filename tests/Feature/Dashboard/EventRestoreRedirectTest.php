<?php

namespace Tests\Feature\Dashboard;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Restore event memulihkan event soft-deleted dan mengarahkan kembali ke halaman
 * tujuan yang diminta klien (`return_to`), default ke halaman detail event.
 */
class EventRestoreRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_restore_defaults_to_event_show(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $event->delete();

        $this->actingAs($admin)
            ->post(route('dashboard.events.restore', $event))
            ->assertRedirect(route('dashboard.events.show', $event->id));
    }

    public function test_restore_with_archived_target_returns_to_archived_index(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $event->delete();

        $this->actingAs($admin)
            ->post(route('dashboard.events.restore', $event), ['return_to' => 'archived'])
            ->assertRedirect(route('dashboard.events.archived'));

        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    public function test_restore_with_index_target_returns_to_events_index(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $event->delete();

        $this->actingAs($admin)
            ->post(route('dashboard.events.restore', $event), ['return_to' => 'index'])
            ->assertRedirect(route('dashboard.events.index'));

        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    public function test_restore_with_explicit_null_target_defaults_to_event_show(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $event->delete();

        $this->actingAs($admin)
            ->post(route('dashboard.events.restore', $event), ['return_to' => null])
            ->assertRedirect(route('dashboard.events.show', $event->id));

        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    public function test_restore_rejects_unknown_return_target(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $event->delete();

        $this->actingAs($admin)
            ->postJson(route('dashboard.events.restore', $event), ['return_to' => 'nowhere'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('return_to');
    }
}

<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Aksi publish / move-to-draft dari halaman detail event. */
class EventStatusUpdateTest extends TestCase
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

    private function eventOwnedBy(User $owner, EventStatus $status = EventStatus::Draft): Event
    {
        return Event::factory()->create([
            'status' => $status,
            'created_by' => $owner->id,
        ]);
    }

    public function test_owner_can_publish_a_draft_event(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('dashboard.events.status', $event), ['status' => EventStatus::Published->value])
            ->assertRedirect(route('dashboard.events.show', $event));

        self::assertSame(EventStatus::Published, $event->refresh()->status);
    }

    public function test_owner_can_move_a_published_event_back_to_draft(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner, EventStatus::Published);

        $this->actingAs($owner)
            ->patch(route('dashboard.events.status', $event), ['status' => EventStatus::Draft->value])
            ->assertRedirect(route('dashboard.events.show', $event));

        self::assertSame(EventStatus::Draft, $event->refresh()->status);
    }

    public function test_publish_is_rejected_when_required_event_data_is_missing(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner);
        $event->update(['description' => '']);

        $this->actingAs($owner)
            ->patch(route('dashboard.events.status', $event), ['status' => EventStatus::Published->value])
            ->assertInvalid(['status']);

        self::assertSame(EventStatus::Draft, $event->refresh()->status);
    }

    public function test_draft_is_allowed_even_when_event_data_is_incomplete(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner, EventStatus::Published);
        $event->update(['description' => '']);

        $this->actingAs($owner)
            ->patch(route('dashboard.events.status', $event), ['status' => EventStatus::Draft->value])
            ->assertRedirect(route('dashboard.events.show', $event));

        self::assertSame(EventStatus::Draft, $event->refresh()->status);
    }

    public function test_admin_who_did_not_create_the_event_is_forbidden(): void
    {
        $owner = $this->admin();
        $otherAdmin = $this->admin();
        $event = $this->eventOwnedBy($owner);

        $this->actingAs($otherAdmin)
            ->patch(route('dashboard.events.status', $event), ['status' => EventStatus::Published->value])
            ->assertForbidden();

        self::assertSame(EventStatus::Draft, $event->refresh()->status);
    }

    public function test_member_is_redirected_by_organizer_middleware(): void
    {
        $event = $this->eventOwnedBy($this->admin());

        $this->actingAs($this->member())
            ->patch(route('dashboard.events.status', $event), ['status' => EventStatus::Published->value])
            ->assertRedirect(route('dashboard'));
    }

    public function test_unknown_status_is_invalid(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner);

        $this->actingAs($owner)
            ->patch(route('dashboard.events.status', $event), ['status' => 'archived'])
            ->assertInvalid(['status']);

        self::assertSame(EventStatus::Draft, $event->refresh()->status);
    }
}

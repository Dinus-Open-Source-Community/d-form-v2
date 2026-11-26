<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Update event tidak lagi mengubah status publikasi; status hanya lewat aksi detail. */
class EventUpdateStatusPreservationTest extends TestCase
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

    /** @return array<string, mixed> */
    private function payload(string $title): array
    {
        return [
            'title' => $title,
            'location' => 'Campus Hall',
            'description' => 'Deskripsi acara.',
            'registration_start' => now()->startOfDay()->toDateTimeString(),
            'registration_end' => now()->addDays(3)->endOfDay()->toDateTimeString(),
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'quota' => 50,
            'price' => '0',
            'session' => 'general',
            'category' => 'rkt',
        ];
    }

    private function eventOwnedBy(User $owner, EventStatus $status): Event
    {
        return Event::factory()->create([
            'created_by' => $owner->id,
            'status' => $status,
        ]);
    }

    public function test_updating_a_published_event_keeps_it_published(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner, EventStatus::Published);

        $this->actingAs($owner)
            ->patch(route('dashboard.events.update', $event), $this->payload('Judul Baru'))
            ->assertRedirect(route('dashboard.events.show', $event));

        self::assertSame(EventStatus::Published, $event->refresh()->status);
    }

    public function test_update_payload_cannot_unpublish_an_event(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner, EventStatus::Published);

        $this->actingAs($owner)
            ->patch(route('dashboard.events.update', $event), [
                ...$this->payload('Judul Baru'),
                'publish' => false,
            ])
            ->assertRedirect(route('dashboard.events.show', $event));

        self::assertSame(EventStatus::Published, $event->refresh()->status);
    }

    public function test_update_payload_cannot_publish_a_draft_event(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner, EventStatus::Draft);

        $this->actingAs($owner)
            ->patch(route('dashboard.events.update', $event), [
                ...$this->payload('Judul Baru'),
                'publish' => true,
            ])
            ->assertRedirect(route('dashboard.events.show', $event));

        self::assertSame(EventStatus::Draft, $event->refresh()->status);
    }
}

<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi IDOR: update event wajib lewat EventPolicy (created_by), bukan hanya permission.
 */
class EventUpdateAuthorizationTest extends TestCase
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

    private function ownedEvent(User $owner): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::Draft,
            'created_by' => $owner->id,
            'title' => 'Event Milik A',
        ]);
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

    public function test_admin_tidak_bisa_mengubah_event_milik_admin_lain(): void
    {
        $owner = $this->admin();
        $other = $this->admin();
        $event = $this->ownedEvent($owner);

        $this->actingAs($other)
            ->patch(route('dashboard.events.update', $event), $this->payload('Diubah Pihak Lain'))
            ->assertForbidden();

        $this->assertSame('Event Milik A', $event->fresh()?->title);
    }

    public function test_pemilik_event_bisa_mengubah_event_nnya(): void
    {
        $owner = $this->admin();
        $event = $this->ownedEvent($owner);

        $this->actingAs($owner)
            ->patch(route('dashboard.events.update', $event), $this->payload('Judul Baru Pemilik'))
            ->assertRedirect(route('dashboard.events.show', $event));

        $this->assertSame('Judul Baru Pemilik', $event->fresh()?->title);
    }

    public function test_super_admin_bisa_mengubah_event_admin_lain(): void
    {
        $owner = $this->admin();
        $superAdmin = $this->admin();
        $superAdmin->assignRole('super-admin');
        $event = $this->ownedEvent($owner);

        $this->actingAs($superAdmin)
            ->patch(route('dashboard.events.update', $event), $this->payload('Diubah Super Admin'))
            ->assertRedirect(route('dashboard.events.show', $event));

        $this->assertSame('Diubah Super Admin', $event->fresh()?->title);
    }

    public function test_sort_yang_bukan_array_tidak_menghasilkan_500(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.events.index', ['sort' => 'foo']))
            ->assertOk();
    }
}

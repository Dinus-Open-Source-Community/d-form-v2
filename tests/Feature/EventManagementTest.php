<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_from_events_index(): void
    {
        $this->get(route('dashboard.events.index'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_member_cannot_view_events_index(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)
            ->get(route('dashboard.events.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_events_index(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard.events.index'))
            ->assertOk();
    }

    public function test_admin_can_create_event_with_banner(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $registrationStart = now()->startOfDay();
        $registrationEnd = now()->addDays(3)->endOfDay();
        $startDate = now()->addDays(10)->startOfDay();
        $endDate = now()->addDays(11)->startOfDay();

        $response = $this->actingAs($admin)->post(route('dashboard.events.store'), [
            'title' => 'Conference Alpha',
            'location' => 'Campus Hall',
            'description' => 'A full description for the event.',
            'registration_start' => $registrationStart->toDateString(),
            'registration_end' => $registrationEnd->toDateString(),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'quota' => 100,
            'price' => '10.000,00',
            'session' => 'general',
            'category' => 'rkt',
            'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 675),
            'publish' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('events', [
            'title' => 'Conference Alpha',
            'location' => 'Campus Hall',
        ]);

        $event = Event::query()->firstOrFail();
        Storage::disk('public')->assertExists($event->banner);
        $this->assertSame('rkt', $event->category);
    }

    public function test_admin_can_create_event_with_multiple_categories(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $registrationStart = now()->startOfDay();
        $registrationEnd = now()->addDays(3)->endOfDay();
        $startDate = now()->addDays(10)->startOfDay();
        $endDate = now()->addDays(11)->startOfDay();

        $response = $this->actingAs($admin)->post(route('dashboard.events.store'), [
            'title' => 'Multi Category Summit',
            'location' => 'Main Hall',
            'description' => 'Description for multi-category event.',
            'registration_start' => $registrationStart->toDateString(),
            'registration_end' => $registrationEnd->toDateString(),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'quota' => 80,
            'price' => '5000',
            'session' => 'general',
            'category' => 'etc, rkt, recruitment',
            'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 675),
            'publish' => false,
        ]);

        $response->assertRedirect();

        $event = Event::query()->where('title', 'Multi Category Summit')->firstOrFail();
        $this->assertSame('rkt,recruitment,etc', $event->category);
    }

    public function test_store_validation_requires_banner(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $registrationStart = now()->startOfDay();
        $registrationEnd = now()->addDays(3)->endOfDay();
        $startDate = now()->addDays(10)->startOfDay();
        $endDate = now()->addDays(11)->startOfDay();

        $response = $this->actingAs($admin)->post(route('dashboard.events.store'), [
            'title' => 'Conference Beta',
            'location' => 'Campus Hall',
            'description' => 'Description here.',
            'registration_start' => $registrationStart->toDateString(),
            'registration_end' => $registrationEnd->toDateString(),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'quota' => 50,
            'price' => '5000',
            'session' => 'general',
            'category' => 'rkt',
        ]);

        $response->assertSessionHasErrors('banner');
    }

    public function test_admin_can_soft_delete_and_restore_event(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('events/banners/x.jpg', 'fake');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = Event::factory()->create([
            'banner' => 'events/banners/x.jpg',
        ]);

        $this->actingAs($admin)
            ->delete(route('dashboard.events.destroy', $event))
            ->assertRedirect(route('dashboard.events.index'));

        $this->assertSoftDeleted('events', ['id' => $event->id]);

        $this->actingAs($admin)
            ->post(route('dashboard.events.restore', $event->id))
            ->assertRedirect(route('dashboard.events.show', $event->id));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'deleted_at' => null,
        ]);
    }
}

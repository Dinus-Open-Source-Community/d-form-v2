<?php

namespace Tests\Feature;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
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
            ->assertRedirect(route('dashboard'));
    }

    public function test_member_can_view_joined_events_portal(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)
            ->get(route('dashboard.user.events'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/User/Events')
                ->where('listMode', 'mine')
            );
    }

    public function test_events_joined_url_resolves_to_member_portal_route(): void
    {
        $route = app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create('/joined', 'GET')
        );

        $this->assertSame('dashboard.user.events', $route->getName());
    }

    public function test_public_event_detail_includes_member_portal_event_url(): void
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'slug' => 'public-demo-event',
        ]);

        $this->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('EventDetail')
                ->where('memberPortalEventUrl', route('dashboard.user.events.show', [
                    'event_segment' => $event->slug,
                ], false))
            );
    }

    public function test_public_event_detail_lists_only_publicly_visible_forms(): void
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'slug' => 'public-forms-event',
        ]);

        $publicForm = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Public Registration Form',
            'visible_for' => [EventFormVisibility::Public->value],
        ]);

        Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Admin Only Form',
            'visible_for' => [EventFormVisibility::Admin->value],
        ]);

        $this->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                ->component('EventDetail')
                ->has('forms', 1)
                ->where('forms.0.id', $publicForm->id)
                ->where('forms.0.title', 'Public Registration Form')
                ->where('forms.0.fill_url', route('dashboard.events.forms.fill', [
                    'event' => $event,
                    'form' => $publicForm,
                ], false))
            );
    }

    public function test_login_honors_local_intended_query(): void
    {
        $user = User::factory()->create();

        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        $path = route('dashboard.events.forms.fill', [
            'event' => $event,
            'form' => $form,
        ], false);

        $this->get(route('auth.login', ['intended' => $path]))->assertOk();

        $this->post(route('auth.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect($path);
    }

    public function test_login_ignores_external_intended(): void
    {
        $user = User::factory()->create();

        foreach (['https://evil.com', '//evil.com', '/\\evil.com'] as $intended) {
            $this->get(route('auth.login', ['intended' => $intended]))->assertOk();
        }

        $this->post(route('auth.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
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

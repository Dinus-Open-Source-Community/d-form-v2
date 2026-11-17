<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardRecentEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Acara milik user tertentu dengan `created_at` eksplisit agar urutan payload deterministik. */
    private function ownedEvent(User $owner, int $minutesAgo): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::Published,
            'created_by' => $owner->id,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    /** Acara tanpa pemilik (`created_by` NULL) tetap terlihat oleh admin maupun super-admin. */
    private function ownerlessEvent(int $minutesAgo): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::Published,
            'created_by' => null,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_admin_sees_own_and_ownerless_events_only(): void
    {
        $admin = $this->userWithRole('admin');
        $otherAdmin = $this->userWithRole('admin');

        $ownEvent = $this->ownedEvent($admin, 2);
        $ownerlessEvent = $this->ownerlessEvent(1);
        $foreignEvent = $this->ownedEvent($otherAdmin, 3);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('recentEvents', fn (Collection $events): bool => collect($events)->pluck('id')->all() === [
                    $ownerlessEvent->id,
                    $ownEvent->id,
                ])
                ->where('recentEvents', fn (Collection $events): bool => ! collect($events)->pluck('id')->contains($foreignEvent->id)));
    }

    public function test_super_admin_sees_events_owned_by_any_admin(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        $admin = $this->userWithRole('admin');
        $otherAdmin = $this->userWithRole('admin');

        $ownerEvent = $this->ownedEvent($admin, 3);
        $ownerlessEvent = $this->ownerlessEvent(2);
        $foreignEvent = $this->ownedEvent($otherAdmin, 1);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('recentEvents', fn (Collection $events): bool => collect($events)->pluck('id')->all() === [
                    $foreignEvent->id,
                    $ownerlessEvent->id,
                    $ownerEvent->id,
                ]));
    }

    public function test_recent_events_are_ordered_newest_first_and_capped_while_stats_count_all_visible(): void
    {
        $admin = $this->userWithRole('admin');

        // 7 acara terlihat admin (miliknya + tanpa pemilik), created_at menurun jelas.
        $events = [
            $this->ownerlessEvent(6),
            $this->ownedEvent($admin, 5),
            $this->ownerlessEvent(4),
            $this->ownedEvent($admin, 3),
            $this->ownerlessEvent(2),
            $this->ownedEvent($admin, 1),
            $this->ownerlessEvent(0),
        ];

        $expectedIds = array_map(
            static fn (Event $event): string => $event->id,
            array_reverse($events),
        );

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->has('recentEvents', 5)
                ->where('recentEvents', fn (Collection $recentEvents): bool => collect($recentEvents)->pluck('id')->all() === array_slice($expectedIds, 0, 5))
                ->where('stats.totalEvents', 7));
    }

    public function test_can_create_event_is_true_for_super_admin_and_admin_with_create_permission(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        $admin = $this->userWithRole('admin');

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canCreateEvent', true));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canCreateEvent', true));
    }

    public function test_can_create_event_reflects_revoked_events_create_permission(): void
    {
        Role::findByName('admin', 'web')->revokePermissionTo('events.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('canCreateEvent', false));
    }
}

<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventCategory;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Daftar acara terarsip: hanya event soft-deleted yang boleh dilihat user,
 * dengan urutan default terbaru diarsipkan.
 */
class ArchivedEventsIndexTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard.events.archived'))->assertRedirect(route('auth.login'));
    }

    public function test_member_without_events_list_is_redirected_to_dashboard(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)->get(route('dashboard.events.archived'))->assertRedirect(route('dashboard'));
    }

    public function test_archived_index_lists_only_soft_deleted_events_owned_by_admin(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        $active = Event::factory()->create(['created_by' => $admin->id]);
        $archived = Event::factory()->create(['created_by' => $admin->id, 'title' => 'Arsip Milik Saya']);
        $archived->delete();
        $foreign = Event::factory()->create(['created_by' => $other->id, 'title' => 'Arsip Orang Lain']);
        $foreign->delete();

        $this->actingAs($admin)
            ->get(route('dashboard.events.archived'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Events/Archived')
                ->has('events.data', 1)
                ->where('events.data.0.id', $archived->id)
                ->where('query.sort.by', 'deleted_at')
                ->where('query.sort.order', 'desc'));
    }

    public function test_super_admin_sees_every_archived_event(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        Event::factory()->create(['created_by' => $this->admin()->id])->delete();
        Event::factory()->create(['created_by' => $this->admin()->id])->delete();

        $this->actingAs($superAdmin)
            ->get(route('dashboard.events.archived'))
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 2));
    }

    public function test_legacy_archived_event_without_creator_is_visible_to_admin(): void
    {
        $admin = $this->admin();
        $legacy = Event::factory()->create(['created_by' => null]);
        $legacy->delete();

        $this->actingAs($admin)
            ->get(route('dashboard.events.archived'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('events.data', 1)
                ->where('events.data.0.id', $legacy->id));
    }

    public function test_archived_list_supports_search_and_category_filter(): void
    {
        $admin = $this->admin();

        $match = Event::factory()->create([
            'created_by' => $admin->id,
            'title' => 'Seminar Keamanan',
            'category' => EventCategory::RKT->value,
        ]);
        $match->delete();

        $other = Event::factory()->create([
            'created_by' => $admin->id,
            'title' => 'Lomba Cepat',
            'category' => EventCategory::NON_RKT->value,
        ]);
        $other->delete();

        $this->actingAs($admin)
            ->get(route('dashboard.events.archived', ['search' => 'Keamanan']))
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.id', $match->id));

        $this->actingAs($admin)
            ->get(route('dashboard.events.archived', ['filter' => ['categories' => [EventCategory::NON_RKT->value]]]))
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.id', $other->id));
    }

    public function test_archived_list_cache_refreshes_after_archiving_an_event(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)->get(route('dashboard.events.archived'))
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 0));

        $event->delete();

        $this->actingAs($admin)->get(route('dashboard.events.archived'))
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.id', $event->id));
    }

    public function test_archived_list_rejects_invalid_sort_field(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson(route('dashboard.events.archived', ['sort' => ['by' => 'created_by']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort.by');
    }
}

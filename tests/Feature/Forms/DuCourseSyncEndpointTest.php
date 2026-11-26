<?php

namespace Tests\Feature\Forms;

use App\Models\DuCourse;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DuCourseSyncEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    public function test_guest_and_non_organizer_cannot_sync(): void
    {
        $this->postJson('/admin/du/courses/sync')->assertUnauthorized();

        // `EnsureOrganizerDashboardAccess` redirects non-organizers instead of 403.
        $member = User::factory()->create();
        $member->assignRole('member');
        $this->actingAs($member)->postJson('/admin/du/courses/sync')->assertRedirect();
    }

    public function test_organizer_syncs_catalog_and_response_has_no_upstream_body(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['courses' => [
            ['uid' => 'course-a', 'title' => 'Kelas A', 'status' => 'ACTIVE'],
        ], 'meta' => ['total_pages' => 1]]])]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->postJson('/admin/du/courses/sync')
            ->assertOk()
            ->assertJsonPath('courses.0.uid', 'course-a')
            ->assertJsonPath('courses.0.title', 'Kelas A')
            ->assertJsonMissingPath('error');

        $this->assertSame(['courses', 'synced_at'], array_keys((array) $response->json()));

        $syncedAt = $response->json('synced_at');
        $this->assertIsString($syncedAt);
        $this->assertNotSame('', $syncedAt);
        $this->assertInstanceOf(Carbon::class, Carbon::parse($syncedAt));

        $this->assertDatabaseHas('du_courses', ['uid' => 'course-a', 'is_active' => true]);
    }

    public function test_upstream_failure_returns_safe_502_without_body_leak(): void
    {
        Http::fake(['*' => Http::response(['secret' => 'do-not-leak'], 503)]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->postJson('/admin/du/courses/sync');

        $response->assertStatus(502);
        $response->assertJsonMissingPath('error');
        $response->assertJsonMissingPath('data');
        $response->assertJsonMissingPath('status');
        $response->assertJsonMissingPath('meta');
        $this->assertStringNotContainsString('do-not-leak', $response->getContent());
        $this->assertSame([], DuCourse::all()->toArray());
    }

    public function test_malformed_catalog_with_empty_course_fields_returns_safe_502(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['courses' => [
            ['uid' => '', 'title' => 'Kelas A', 'status' => 'ACTIVE'],
        ], 'meta' => ['total_pages' => 1]]])]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->postJson('/admin/du/courses/sync');

        $response->assertStatus(502)
            ->assertJson(['message' => 'Katalog kelas DU tidak dapat dimuat.']);
        $this->assertSame([], DuCourse::all()->toArray());
    }

    public function test_route_is_rate_limited(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['courses' => [], 'meta' => ['total_pages' => 1]]])]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->actingAs($admin)->postJson('/admin/du/courses/sync')->assertOk();
        }

        $this->actingAs($admin)->postJson('/admin/du/courses/sync')->assertStatus(429)->assertHeader('Retry-After');
    }
}

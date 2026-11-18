<?php

namespace Tests\Feature\Dashboard;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Direktori pengguna admin: whitelist per_page, baris dengan otorisasi server,
 * filter kembali setelah simpan, dan route create/edit yang sudah dihapus.
 */
class UserManagementDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function inertiaGet(string $uri): TestResponse
    {
        return $this->get($uri, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        ]);
    }

    private function superAdmin(): User
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        return $superAdmin;
    }

    /**
     * @return array<string, mixed>
     */
    private function redirectQuery(TestResponse $response): array
    {
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    public function test_index_honours_per_page_whitelist(): void
    {
        $this->actingAs($this->superAdmin());

        $allowed = $this->inertiaGet('/admin/users?per_page=50')->assertOk();
        $this->assertSame(50, (int) $allowed->json('props.users.per_page'));
        $this->assertSame(50, (int) $allowed->json('props.query.per_page'));

        $fallback = $this->inertiaGet('/admin/users?per_page=999')->assertOk();
        $this->assertSame(20, (int) $fallback->json('props.users.per_page'));
        $this->assertSame(20, (int) $fallback->json('props.query.per_page'));
    }

    public function test_index_row_exposes_avatar_and_server_permissions(): void
    {
        $superAdmin = $this->superAdmin();

        $otherSuperAdmin = User::factory()->create();
        $otherSuperAdmin->assignRole('super-admin');

        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($superAdmin);

        $response = $this->inertiaGet('/admin/users')->assertOk();
        $this->assertSame('Dashboard/Users/Index', $response->json('component'));

        $rows = collect($response->json('props.users.data'))->keyBy('id');

        $this->assertFalse($rows[$superAdmin->id]['can_edit']);
        $this->assertFalse($rows[$superAdmin->id]['can_delete']);

        $this->assertFalse($rows[$otherSuperAdmin->id]['can_edit']);
        $this->assertFalse($rows[$otherSuperAdmin->id]['can_delete']);

        $this->assertTrue($rows[$member->id]['can_edit']);
        $this->assertTrue($rows[$member->id]['can_delete']);

        $this->assertNull($rows[$member->id]['avatar_url']);
    }

    public function test_index_accepts_edit_query_param(): void
    {
        $this->actingAs($this->superAdmin());

        $member = User::factory()->create();
        $member->assignRole('member');

        $response = $this->inertiaGet('/admin/users?edit='.$member->id)->assertOk();

        $this->assertSame($member->id, $response->json('props.query.edit'));
    }

    public function test_index_accepts_create_query_param(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->inertiaGet('/admin/users?create=1')->assertOk();

        $this->assertTrue($response->json('props.query.create'));
    }

    public function test_create_and_edit_pages_are_gone(): void
    {
        $this->actingAs($this->superAdmin());

        $member = User::factory()->create();
        $member->assignRole('member');

        $this->get('/admin/users/create')->assertNotFound();
        $this->get('/admin/users/'.$member->id.'/edit')->assertNotFound();

        $this->assertFalse(Route::has('dashboard.users.create'));
        $this->assertFalse(Route::has('dashboard.users.edit'));
    }

    public function test_store_redirect_preserves_return_filters(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('dashboard.users.store'), [
                'name' => 'Budi Direktori',
                'email' => 'budi-direktori@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'member',
                'return_filters' => [
                    'search' => 'budi',
                    'role' => 'member',
                    'page' => 2,
                ],
            ]);

        $response->assertRedirect();
        $this->assertSame(
            ['search' => 'budi', 'role' => 'member', 'page' => '2'],
            $this->redirectQuery($response)
        );
        $this->assertSame(route('dashboard.users.index'), strtok((string) $response->headers->get('Location'), '?'));
    }

    public function test_update_redirect_preserves_return_filters(): void
    {
        $superAdmin = $this->superAdmin();

        $target = User::factory()->create();
        $target->assignRole('member');

        $response = $this->actingAs($superAdmin)
            ->put(route('dashboard.users.update', $target), [
                'name' => 'Siti Direktori',
                'email' => $target->email,
                'role' => 'member',
                'return_filters' => [
                    'search' => 'siti',
                    'role' => '',
                ],
            ]);

        $response->assertRedirect();
        $this->assertSame(['search' => 'siti'], $this->redirectQuery($response));
    }

    public function test_show_page_exposes_role_options(): void
    {
        $this->actingAs($this->superAdmin());

        $member = User::factory()->create();
        $member->assignRole('member');

        $this->get(route('dashboard.users.show', $member))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Users/Show')
                ->where('roleOptions.0.label', 'Admin')
                ->where('roleOptions.0.description', 'Kelola event dan formulir. Tidak bisa mengelola pengguna.')
                ->has('roleOptions', 4));
    }
}

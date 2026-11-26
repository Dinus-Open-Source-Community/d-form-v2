<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.github.client_id' => 'github-client-id',
            'services.github.client_secret' => 'github-client-secret',
        ]);
    }

    public function test_page_receives_connections_for_linked_user(): void
    {
        $user = $this->member(['google_id' => 'google-1']);

        $this->actingAs($this->sessionUser($user))
            ->get(route('dashboard.profile'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->component('Dashboard/Profile')
                ->has('connections', 2)
                ->where('connections.0.provider', 'google')
                ->where('connections.0.connected', true)
                ->where('connections.0.can_disconnect', true)
                ->where('connections.0.configured', true)
                ->where('connections.1.provider', 'github')
                ->where('connections.1.connected', false)
                ->where('connections.1.can_disconnect', false)
                ->where('connections.1.configured', true)
            );
    }

    public function test_can_disconnect_false_for_last_credential(): void
    {
        $user = $this->member([
            'password' => null,
            'google_id' => 'google-1',
            'github_id' => null,
        ]);

        $this->actingAs($this->sessionUser($user))
            ->get(route('dashboard.profile'))
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('connections.0.connected', true)
                ->where('connections.0.can_disconnect', false)
            );
    }

    public function test_can_disconnect_true_when_password_exists(): void
    {
        $user = $this->member(['google_id' => 'google-1']);

        $this->actingAs($this->sessionUser($user))
            ->get(route('dashboard.profile'))
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('connections.0.can_disconnect', true)
            );
    }

    public function test_can_disconnect_true_when_other_provider_connected(): void
    {
        $user = $this->member([
            'password' => null,
            'google_id' => 'google-1',
            'github_id' => 'github-1',
        ]);

        $this->actingAs($this->sessionUser($user))
            ->get(route('dashboard.profile'))
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('connections.0.connected', true)
                ->where('connections.0.can_disconnect', true)
                ->where('connections.1.connected', true)
                ->where('connections.1.can_disconnect', true)
            );
    }

    public function test_configured_false_when_client_id_empty(): void
    {
        config([
            'services.google.client_id' => '',
            'services.google.client_secret' => '',
        ]);

        $user = $this->member();

        $this->actingAs($this->sessionUser($user))
            ->get(route('dashboard.profile'))
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('connections.0.provider', 'google')
                ->where('connections.0.configured', false)
                ->where('connections.1.provider', 'github')
                ->where('connections.1.configured', true)
            );
    }

    public function test_page_redirects_guest(): void
    {
        $this->get(route('dashboard.profile'))->assertRedirectToRoute('auth.login');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('member');

        return $user;
    }

    /**
     * Sesi hanya menyimpan id; kolom OAuth tidak ikut dimuat oleh ExplicitColumnUserProvider.
     */
    private function sessionUser(User $user): User
    {
        return User::query()->select(User::AUTH_SESSION_COLUMNS)->findOrFail($user->getKey());
    }
}

<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;
use Mockery;
use Tests\TestCase;

class ProfileConnectionFlowTest extends TestCase
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

    public function test_guest_cannot_start_bind(): void
    {
        $this->get($this->redirectRoute('google'))->assertRedirectToRoute('auth.login');
    }

    public function test_guest_cannot_destroy_connection(): void
    {
        $this->delete($this->destroyRoute('google'))->assertRedirectToRoute('auth.login');
    }

    public function test_bind_start_redirects_to_provider_and_sets_intent(): void
    {
        $user = $this->member();
        $this->mockProviderRedirect('google', 'https://accounts.google.test/authorize');

        $response = $this->actingAs($user)->get($this->redirectRoute('google'));

        $response->assertRedirect('https://accounts.google.test/authorize');
        $response->assertSessionHas('oauth_bind', function (array $intent) use ($user): bool {
            return ($intent['provider'] ?? null) === 'google'
                && ($intent['user_id'] ?? null) === $user->getKey()
                && is_int($intent['expires_at'] ?? null)
                && $intent['expires_at'] > now()->getTimestamp();
        });
    }

    public function test_callback_with_valid_intent_links_provider(): void
    {
        $user = $this->member([
            'name' => 'Nama Asli',
            'email' => 'asli@example.com',
            'avatar' => 'avatars/asli.jpg',
            'google_id' => null,
        ]);
        $verifiedAt = $user->email_verified_at;

        $this->actingAs($this->sessionUser($user));
        $this->withSession(['oauth_bind' => $this->bindIntent('google', $user)]);
        $this->mockProviderUser('google', 'google-777');

        $response = $this->get(route('auth.google.callback'));

        $this->assertLandsOnConnections($response);
        $this->assertToast($response, 'success');
        $response->assertSessionMissing('oauth_bind');

        $user->refresh();
        $this->assertSame('google-777', $user->google_id);
        $this->assertSame('Nama Asli', $user->name);
        $this->assertSame('asli@example.com', $user->email);
        $this->assertSame('avatars/asli.jpg', $user->avatar);
        $this->assertEquals($verifiedAt, $user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_without_intent_while_authenticated_does_not_link(): void
    {
        $user = $this->member(['google_id' => null]);

        $this->actingAs($this->sessionUser($user));
        Socialite::shouldReceive('driver')->never();

        $response = $this->get(route('auth.google.callback'));

        $this->assertLandsOnConnections($response);
        $this->assertToast($response, 'error');
        $this->assertNull($user->refresh()->google_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_with_expired_intent_does_not_link(): void
    {
        $user = $this->member(['google_id' => null]);

        $this->actingAs($this->sessionUser($user));
        $this->withSession(['oauth_bind' => $this->bindIntent('google', $user, -1)]);
        Socialite::shouldReceive('driver')->never();

        $response = $this->get(route('auth.google.callback'));

        $this->assertLandsOnConnections($response);
        $this->assertToast($response, 'error');
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_callback_with_other_provider_intent_does_not_link(): void
    {
        $user = $this->member(['google_id' => null]);

        $this->actingAs($this->sessionUser($user));
        $this->withSession(['oauth_bind' => $this->bindIntent('github', $user)]);
        Socialite::shouldReceive('driver')->never();

        $response = $this->get(route('auth.google.callback'));

        $this->assertLandsOnConnections($response);
        $this->assertToast($response, 'error');
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_callback_with_intent_for_another_user_does_not_link(): void
    {
        $user = $this->member(['google_id' => null]);
        $other = $this->member(['google_id' => null]);

        $this->actingAs($this->sessionUser($user));
        $this->withSession(['oauth_bind' => $this->bindIntent('google', $other)]);
        Socialite::shouldReceive('driver')->never();

        $response = $this->get(route('auth.google.callback'));

        $this->assertLandsOnConnections($response);
        $this->assertToast($response, 'error');
        $this->assertNull($user->refresh()->google_id);
        $this->assertNull($other->refresh()->google_id);
    }

    public function test_callback_with_id_owned_by_another_user_does_not_overwrite(): void
    {
        $owner = $this->member(['google_id' => 'google-taken']);
        $user = $this->member(['google_id' => null]);

        $this->actingAs($this->sessionUser($user));
        $this->withSession(['oauth_bind' => $this->bindIntent('google', $user)]);
        $this->mockProviderUser('google', 'google-taken');

        $response = $this->get(route('auth.google.callback'));

        $this->assertLandsOnConnections($response);
        $this->assertToast($response, 'error');
        $this->assertSame('google-taken', $owner->refresh()->google_id);
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_cancelled_consent_redirects_to_profile_connections(): void
    {
        $user = $this->member(['google_id' => null]);

        $this->actingAs($this->sessionUser($user));
        $this->withSession(['oauth_bind' => $this->bindIntent('google', $user)]);
        $this->mockProviderException('google', new \RuntimeException('access_denied'));

        $response = $this->get(route('auth.google.callback'));

        $this->assertLandsOnConnections($response);
        $this->assertToast($response, 'error');
        $this->assertNull($user->refresh()->google_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_unconfigured_provider_redirects_with_error_toast(): void
    {
        config([
            'services.google.client_id' => '',
            'services.google.client_secret' => '',
        ]);

        $user = $this->member();
        Socialite::shouldReceive('driver')->never();

        $response = $this->actingAs($user)->get($this->redirectRoute('google'));

        $this->assertLandsOnConnections($response);
        $this->assertToast($response, 'error');
        $response->assertSessionMissing('oauth_bind');
    }

    public function test_destroy_clears_connection(): void
    {
        $user = $this->member(['google_id' => 'google-1']);

        $response = $this->actingAs($this->sessionUser($user))->delete($this->destroyRoute('google'));

        $response->assertRedirect();
        $this->assertToast($response, 'success');
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_destroy_blocked_for_last_credential(): void
    {
        $user = $this->member([
            'password' => null,
            'google_id' => 'google-only',
            'github_id' => null,
        ]);

        $response = $this->actingAs($this->sessionUser($user))->delete($this->destroyRoute('google'));

        $response->assertRedirect();
        $this->assertToast($response, 'error');
        $this->assertSame('google-only', $user->refresh()->google_id);
    }

    public function test_unknown_provider_returns_404(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get('/profile/connections/facebook')->assertNotFound();
        $this->actingAs($user)->delete('/profile/connections/facebook')->assertNotFound();
    }

    public function test_connection_routes_are_throttled(): void
    {
        $user = $this->member();
        $this->mockProviderRedirect('google', 'https://accounts.google.test/authorize');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->actingAs($user)->get($this->redirectRoute('google'))->assertRedirect();
        }

        $this->actingAs($user)->get($this->redirectRoute('google'))->assertStatus(429);
    }

    private function redirectRoute(string $provider): string
    {
        return route('dashboard.profile.connection.redirect', ['provider' => $provider]);
    }

    private function destroyRoute(string $provider): string
    {
        return route('dashboard.profile.connection.destroy', ['provider' => $provider]);
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
     * Sesi hanya menyimpan id; provider dimuat lewat ExplicitColumnUserProvider tanpa kolom OAuth.
     */
    private function sessionUser(User $user): User
    {
        return User::query()->select(User::AUTH_SESSION_COLUMNS)->findOrFail($user->getKey());
    }

    /**
     * @return array{provider: string, user_id: string, expires_at: int}
     */
    private function bindIntent(string $provider, User $user, int $expiresInMinutes = 10): array
    {
        return [
            'provider' => $provider,
            'user_id' => $user->getKey(),
            'expires_at' => now()->addMinutes($expiresInMinutes)->getTimestamp(),
        ];
    }

    private function mockProviderRedirect(string $provider, string $target): void
    {
        Socialite::shouldReceive('driver')->with($provider)->andReturnSelf();
        Socialite::shouldReceive('redirect')->andReturn(redirect($target));
    }

    private function mockProviderUser(string $provider, string $providerId): void
    {
        $socialiteUser = Mockery::mock(SocialiteTwoUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($providerId);

        Socialite::shouldReceive('driver')->with($provider)->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialiteUser);
    }

    private function mockProviderException(string $provider, \Exception $exception): void
    {
        Socialite::shouldReceive('driver')->with($provider)->andReturnSelf();
        Socialite::shouldReceive('user')->andThrow($exception);
    }

    private function assertLandsOnConnections(TestResponse $response): void
    {
        $response->assertRedirect(route('dashboard.profile').'#koneksi');
    }

    private function assertToast(TestResponse $response, string $type): void
    {
        $response->assertSessionHas('inertia.flash_data', function (array $flash) use ($type): bool {
            return ($flash['toast']['type'] ?? null) === $type
                && is_string($flash['toast']['message'] ?? null)
                && $flash['toast']['message'] !== '';
        });
    }
}

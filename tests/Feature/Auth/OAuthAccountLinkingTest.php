<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class OAuthAccountLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_new_google_user_with_verified_email_is_created_and_logged_in(): void
    {
        $this->mockProviderCallback('google', 'new-google@example.com', ['email_verified' => true]);

        $this->get(route('auth.google.callback'))->assertRedirect();

        $user = User::query()->where('email', 'new-google@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('provider-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('member'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_new_google_user_with_unverified_email_is_rejected_without_creating_account(): void
    {
        $this->mockProviderCallback('google', 'unverified-google@example.com', ['email_verified' => false]);

        $this->assertRejectedWithNeutralMessage($this->get(route('auth.google.callback')));

        $this->assertDatabaseMissing('users', ['email' => 'unverified-google@example.com']);
    }

    public function test_google_unverified_email_does_not_link_existing_account(): void
    {
        $user = $this->createMember(['email' => 'link-target@example.com']);

        $this->mockProviderCallback('google', 'link-target@example.com', ['email_verified' => false]);

        $this->assertRejectedWithNeutralMessage($this->get(route('auth.google.callback')));

        $user->refresh();
        $this->assertNull($user->google_id);
    }

    public function test_google_verified_email_links_existing_account_case_insensitively(): void
    {
        $user = $this->createMember(['email' => 'case-target@example.com'], verified: false);

        $this->mockProviderCallback('google', 'Case-Target@Example.com', ['email_verified' => true]);

        $this->get(route('auth.google.callback'))->assertRedirect();

        $user->refresh();
        $this->assertSame('provider-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_linked_user_logs_in_even_when_provider_email_is_unverified(): void
    {
        $user = $this->createMember([
            'email' => 'linked-google@example.com',
            'google_id' => 'provider-123',
        ]);

        $this->mockProviderCallback('google', 'linked-google@example.com', ['email_verified' => false]);

        $this->get(route('auth.google.callback'))->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_google_email_matching_account_with_different_google_id_is_rejected(): void
    {
        $user = $this->createMember([
            'email' => 'conflict-google@example.com',
            'google_id' => 'other-google-account',
        ]);

        $this->mockProviderCallback('google', 'conflict-google@example.com', ['email_verified' => true]);

        $this->assertRejectedWithNeutralMessage($this->get(route('auth.google.callback')));

        $this->assertSame('other-google-account', $user->refresh()->google_id);
    }

    public function test_google_soft_deleted_email_is_rejected_without_creating_account(): void
    {
        $this->createMember(['email' => 'deleted-google@example.com'])->delete();

        $this->mockProviderCallback('google', 'deleted-google@example.com', ['email_verified' => true]);

        $this->assertRejectedWithNeutralMessage($this->get(route('auth.google.callback')));

        $this->assertSame(0, User::query()->where('email', 'deleted-google@example.com')->count());
        $this->assertSame(1, User::withTrashed()->where('email', 'deleted-google@example.com')->count());
    }

    public function test_google_rejects_string_false_email_verified_claim(): void
    {
        $this->mockProviderCallback('google', 'string-false@example.com', ['email_verified' => 'false']);

        $this->assertRejectedWithNeutralMessage($this->get(route('auth.google.callback')));

        $this->assertDatabaseMissing('users', ['email' => 'string-false@example.com']);
    }

    public function test_google_missing_email_is_rejected_without_creating_account(): void
    {
        $this->mockProviderCallback('google', null, ['email_verified' => true]);

        $this->assertRejectedWithNeutralMessage($this->get(route('auth.google.callback')));

        $this->assertSame(0, User::query()->whereNotNull('google_id')->count());
    }

    public function test_new_github_user_with_verified_email_is_created_and_logged_in(): void
    {
        $this->mockProviderCallback('github', 'new-github@example.com');

        $this->get(route('auth.github.callback'))->assertRedirect();

        $user = User::query()->where('email', 'new-github@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('provider-123', $user->github_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('member'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_github_without_email_does_not_create_synthetic_account(): void
    {
        $this->mockProviderCallback('github', null);

        $this->assertRejectedWithNeutralMessage($this->get(route('auth.github.callback')));

        $this->assertSame(0, User::query()->where('email', 'like', '%@github.local')->count());
        $this->assertSame(0, User::query()->whereNotNull('github_id')->count());
    }

    public function test_github_without_email_does_not_link_existing_local_account(): void
    {
        $user = $this->createMember(['email' => 'github-existing@example.com']);

        $this->mockProviderCallback('github', null);

        $this->assertRejectedWithNeutralMessage($this->get(route('auth.github.callback')));

        $this->assertNull($user->refresh()->github_id);
    }

    public function test_github_linked_user_logs_in_without_email(): void
    {
        $user = $this->createMember([
            'email' => 'github-linked@example.com',
            'github_id' => 'provider-123',
        ]);

        $this->mockProviderCallback('github', null);

        $this->get(route('auth.github.callback'))->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_github_verified_email_links_existing_account_case_insensitively(): void
    {
        $user = $this->createMember(['email' => 'github-case@example.com'], verified: false);

        $this->mockProviderCallback('github', 'GitHub-Case@Example.com');

        $this->get(route('auth.github.callback'))->assertRedirect();

        $user->refresh();
        $this->assertSame('provider-123', $user->github_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_github_email_matching_account_with_different_github_id_is_rejected(): void
    {
        $user = $this->createMember([
            'email' => 'conflict-github@example.com',
            'github_id' => 'other-github-account',
        ]);

        $this->mockProviderCallback('github', 'conflict-github@example.com');

        $this->assertRejectedWithNeutralMessage($this->get(route('auth.github.callback')));

        $this->assertSame('other-github-account', $user->refresh()->github_id);
    }

    public function test_github_soft_deleted_email_is_rejected_without_creating_account(): void
    {
        $this->createMember(['email' => 'deleted-github@example.com'])->delete();

        $this->mockProviderCallback('github', 'deleted-github@example.com');

        $this->assertRejectedWithNeutralMessage($this->get(route('auth.github.callback')));

        $this->assertSame(0, User::query()->where('email', 'deleted-github@example.com')->count());
        $this->assertSame(1, User::withTrashed()->where('email', 'deleted-github@example.com')->count());
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function mockProviderCallback(string $provider, ?string $email, array $raw = [], string $providerId = 'provider-123'): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($providerId);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn('OAuth User');
        $socialiteUser->shouldReceive('getNickname')->andReturn('oauth-user');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
        $socialiteUser->shouldReceive('getRaw')->andReturn($raw);

        Socialite::shouldReceive('driver')->with($provider)->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialiteUser);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMember(array $attributes, bool $verified = true): User
    {
        $factory = User::factory();

        if (! $verified) {
            $factory = $factory->unverified();
        }

        $user = $factory->create($attributes);
        $user->assignRole('member');

        return $user;
    }

    private function assertRejectedWithNeutralMessage(TestResponse $response): void
    {
        $response
            ->assertRedirect(route('auth.login'))
            ->assertSessionHas('inertia.flash_data', function (array $flash): bool {
                return ($flash['toast']['type'] ?? null) === 'error'
                    && is_string($flash['toast']['message'] ?? null)
                    && $flash['toast']['message'] !== '';
            });

        $this->assertGuest();
    }
}

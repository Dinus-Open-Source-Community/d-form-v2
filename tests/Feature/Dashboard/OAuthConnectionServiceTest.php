<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ProviderConnectionResult;
use App\Enums\ProviderDisconnectionResult;
use App\Enums\SocialProvider;
use App\Models\User;
use App\Services\Auth\OAuthConnectionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Mockery;
use PDOException;
use Tests\TestCase;

class OAuthConnectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private OAuthConnectionService $connections;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connections = app(OAuthConnectionService::class);
    }

    public function test_connect_sets_provider_id_without_touching_identity(): void
    {
        $user = User::factory()->create([
            'name' => 'Nama Asli',
            'email' => 'asli@example.com',
            'avatar' => 'avatars/asli.jpg',
            'google_id' => null,
        ]);
        $verifiedAt = $user->email_verified_at;

        $result = $this->connections->connect($user, SocialProvider::Google, $this->identity('google-1'));

        $this->assertSame(ProviderConnectionResult::Connected, $result);

        $user->refresh();
        $this->assertSame('google-1', $user->google_id);
        $this->assertSame('Nama Asli', $user->name);
        $this->assertSame('asli@example.com', $user->email);
        $this->assertSame('avatars/asli.jpg', $user->avatar);
        $this->assertEquals($verifiedAt, $user->email_verified_at);
    }

    public function test_connect_is_idempotent_for_same_user(): void
    {
        $user = User::factory()->create(['google_id' => 'google-1']);

        $result = $this->connections->connect($user, SocialProvider::Google, $this->identity('google-1'));

        $this->assertSame(ProviderConnectionResult::AlreadyConnected, $result);
        $this->assertSame('google-1', $user->refresh()->google_id);
    }

    public function test_connect_rejects_id_owned_by_another_user(): void
    {
        $owner = User::factory()->create(['google_id' => 'google-taken']);
        $user = User::factory()->create(['google_id' => null]);

        $result = $this->connections->connect($user, SocialProvider::Google, $this->identity('google-taken'));

        $this->assertSame(ProviderConnectionResult::OwnedByAnotherUser, $result);
        $this->assertNull($user->refresh()->google_id);
        $this->assertSame('google-taken', $owner->refresh()->google_id);
    }

    public function test_connect_rejects_empty_provider_id(): void
    {
        $user = User::factory()->create(['google_id' => null]);

        $result = $this->connections->connect($user, SocialProvider::Google, $this->identity('   '));

        $this->assertSame(ProviderConnectionResult::InvalidIdentity, $result);
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_connect_does_not_overwrite_other_user_row(): void
    {
        $owner = User::factory()->create([
            'name' => 'Pemilik Lama',
            'email' => 'pemilik@example.com',
            'google_id' => 'google-taken',
        ]);
        $user = User::factory()->create([
            'name' => 'Pendatang',
            'email' => 'pendatang@example.com',
            'google_id' => null,
        ]);

        $this->connections->connect($user, SocialProvider::Google, $this->identity('google-taken'));

        $owner->refresh();
        $this->assertSame('Pemilik Lama', $owner->name);
        $this->assertSame('pemilik@example.com', $owner->email);
        $this->assertSame('google-taken', $owner->google_id);
        $this->assertSame('Pendatang', $user->refresh()->name);
    }

    public function test_connect_reports_stale_trashed_owner(): void
    {
        $staleOwner = User::factory()->create(['github_id' => 'github-stale']);
        $staleOwner->delete();

        $user = User::factory()->create(['github_id' => null]);

        $result = $this->connections->connect($user, SocialProvider::Github, $this->identity('github-stale'));

        $this->assertSame(ProviderConnectionResult::OwnedByAnotherUser, $result);
        $this->assertNull($user->refresh()->github_id);
    }

    public function test_connect_maps_unique_violation_to_owned_by_another_user(): void
    {
        $user = User::factory()->create(['google_id' => null]);

        Event::listen('eloquent.saving: '.User::class, function (User $saving): void {
            if ($saving->getAttribute('google_id') !== 'racing-id') {
                return;
            }

            $previous = new PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry');
            $previous->errorInfo = ['23000', 1062, 'Duplicate entry'];

            throw new UniqueConstraintViolationException('testing', 'update users set google_id = ?', [], $previous);
        });

        try {
            $result = $this->connections->connect($user, SocialProvider::Google, $this->identity('racing-id'));
        } finally {
            Event::forget('eloquent.saving: '.User::class);
        }

        $this->assertSame(ProviderConnectionResult::OwnedByAnotherUser, $result);
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_disconnect_clears_column(): void
    {
        $user = User::factory()->create(['google_id' => 'google-1']);

        $result = $this->connections->disconnect($user, SocialProvider::Google);

        $this->assertSame(ProviderDisconnectionResult::Disconnected, $result);
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_disconnect_reports_not_connected(): void
    {
        $user = User::factory()->create(['google_id' => null]);

        $result = $this->connections->disconnect($user, SocialProvider::Google);

        $this->assertSame(ProviderDisconnectionResult::NotConnected, $result);
    }

    public function test_disconnect_blocks_when_no_credential_remains(): void
    {
        $user = User::factory()->create([
            'password' => null,
            'google_id' => 'google-only',
            'github_id' => null,
        ]);

        $result = $this->connections->disconnect($user, SocialProvider::Google);

        $this->assertSame(ProviderDisconnectionResult::LastCredential, $result);
        $this->assertSame('google-only', $user->refresh()->google_id);
    }

    public function test_disconnect_allowed_when_password_exists(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-1',
            'github_id' => null,
        ]);

        $result = $this->connections->disconnect($user, SocialProvider::Google);

        $this->assertSame(ProviderDisconnectionResult::Disconnected, $result);
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_disconnect_allowed_when_other_provider_exists(): void
    {
        $user = User::factory()->create([
            'password' => null,
            'google_id' => 'google-1',
            'github_id' => 'github-1',
        ]);

        $result = $this->connections->disconnect($user, SocialProvider::Google);

        $this->assertSame(ProviderDisconnectionResult::Disconnected, $result);
        $this->assertNull($user->refresh()->google_id);
        $this->assertSame('github-1', $user->github_id);
    }

    private function identity(string $providerId): SocialiteUser
    {
        $identity = Mockery::mock(SocialiteUser::class);
        $identity->shouldReceive('getId')->andReturn($providerId);

        return $identity;
    }
}

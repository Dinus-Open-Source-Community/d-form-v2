<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Services\Auth\TwoFactorPinService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengaturan PIN faktor kedua dari halaman profil (spec §6.3, §6.5, plan Task 6).
 */
class TwoFactorSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** PIN lama akun uji. */
    private const PIN = '123456';

    /** PIN baru pada aksi ubah. */
    private const NEW_PIN = '654321';

    /** Kata sandi lokal akun uji. */
    private const PASSWORD = 'rahasia-uji';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login_on_every_settings_route(): void
    {
        $this->post(route('profile.two-factor.enable'), ['pin' => self::PIN])
            ->assertRedirect(route('auth.login'));
        $this->put(route('profile.two-factor.change'), ['pin' => self::NEW_PIN])
            ->assertRedirect(route('auth.login'));
        $this->delete(route('profile.two-factor.disable'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_the_settings_routes_require_the_second_factor(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)
            ->put(route('profile.two-factor.change'), ['pin' => self::NEW_PIN])
            ->assertRedirect(route('two-factor.challenge'));

        $this->actingAsPinUser($user)
            ->delete(route('profile.two-factor.disable'))
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_a_verified_session_reaches_change_and_disable_instead_of_the_challenge(): void
    {
        $user = $this->pinUser();

        // Gerbang faktor kedua sudah lolos: permintaan sampai ke controller (error PIN,
        // bukan redirect tantangan) pada kedua aksi yang membutuhkan PIN saat ini.
        $this->actingAsVerifiedPinUser($user)
            ->from(route('dashboard.profile'))
            ->put(route('profile.two-factor.change'), [
                'pin' => self::NEW_PIN,
                'current_pin' => '000000',
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('dashboard.profile'))
            ->assertSessionHasErrors('current_pin');

        $this->actingAsVerifiedPinUser($user)
            ->from(route('dashboard.profile'))
            ->delete(route('profile.two-factor.disable'), [
                'current_pin' => '000000',
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('dashboard.profile'))
            ->assertSessionHasErrors('current_pin');
    }

    public function test_enable_requires_the_current_password_when_the_account_has_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);

        $this->actingAs($user)
            ->from(route('dashboard.profile'))
            ->post(route('profile.two-factor.enable'), ['pin' => self::PIN])
            ->assertRedirect(route('dashboard.profile'))
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->from(route('dashboard.profile'))
            ->post(route('profile.two-factor.enable'), [
                'pin' => self::PIN,
                'current_password' => 'kata-sandi-salah',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseMissing('user_pin_credentials', ['user_id' => $user->getKey()]);
    }

    public function test_enable_with_the_current_password_activates_the_pin(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);

        $this->actingAs($user)
            ->from(route('dashboard.profile'))
            ->post(route('profile.two-factor.enable'), [
                'pin' => self::PIN,
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('dashboard.profile'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::PIN, $this->pinHashOf($user)));
        $this->assertSame($user->getKey(), session('two_factor_user_id'));
    }

    public function test_an_oauth_only_account_can_enable_from_its_active_session(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->from(route('dashboard.profile'))
            ->post(route('profile.two-factor.enable'), ['pin' => self::PIN])
            ->assertRedirect(route('dashboard.profile'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::PIN, $this->pinHashOf($user)));
    }

    public function test_enable_is_refused_while_a_pin_is_already_active(): void
    {
        $user = $this->pinUser();

        $this->actingAsVerifiedPinUser($user)
            ->post(route('profile.two-factor.enable'), [
                'pin' => self::NEW_PIN,
                'current_password' => self::PASSWORD,
            ])
            ->assertSessionHasErrors('pin');

        $this->assertTrue(Hash::check(self::PIN, $this->pinHashOf($user)));
        $this->assertFalse(Hash::check(self::NEW_PIN, $this->pinHashOf($user)));
    }

    public function test_change_requires_the_current_pin(): void
    {
        $user = $this->pinUser();

        $this->actingAsVerifiedPinUser($user)
            ->from(route('dashboard.profile'))
            ->put(route('profile.two-factor.change'), [
                'pin' => self::NEW_PIN,
                'current_pin' => '000000',
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('dashboard.profile'))
            ->assertSessionHasErrors('current_pin');

        $this->assertTrue(Hash::check(self::PIN, $this->pinHashOf($user)));
    }

    public function test_change_requires_the_current_password_when_the_account_has_one(): void
    {
        $user = $this->pinUser();

        $this->actingAsVerifiedPinUser($user)
            ->from(route('dashboard.profile'))
            ->put(route('profile.two-factor.change'), [
                'pin' => self::NEW_PIN,
                'current_pin' => self::PIN,
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check(self::PIN, $this->pinHashOf($user)));
    }

    public function test_change_replaces_the_pin_and_rotates_the_session(): void
    {
        $user = $this->pinUser();
        $this->actingAsVerifiedPinUser($user)->get(route('dashboard.profile'))->assertOk();
        $sessionIdBefore = $this->app['session.store']->getId();

        $this->actingAsVerifiedPinUser($user)
            ->from(route('dashboard.profile'))
            ->put(route('profile.two-factor.change'), [
                'pin' => self::NEW_PIN,
                'current_pin' => self::PIN,
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('dashboard.profile'))
            ->assertSessionHasNoErrors();

        $hash = $this->pinHashOf($user);
        $this->assertTrue(Hash::check(self::NEW_PIN, $hash));
        $this->assertFalse(Hash::check(self::PIN, $hash));
        $this->assertNotSame($sessionIdBefore, $this->app['session.store']->getId());
        $this->assertSame($user->getKey(), session('two_factor_user_id'));
    }

    public function test_disable_requires_the_current_pin(): void
    {
        $user = $this->pinUser();

        $this->actingAsVerifiedPinUser($user)
            ->from(route('dashboard.profile'))
            ->delete(route('profile.two-factor.disable'), [
                'current_pin' => '000000',
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('dashboard.profile'))
            ->assertSessionHasErrors('current_pin');

        $this->assertDatabaseHas('user_pin_credentials', ['user_id' => $user->getKey()]);
    }

    public function test_disable_removes_the_pin_and_clears_the_session_markers(): void
    {
        $user = $this->pinUser();
        $this->actingAsVerifiedPinUser($user)->get(route('dashboard.profile'))->assertOk();
        $sessionIdBefore = $this->app['session.store']->getId();

        $this->actingAsVerifiedPinUser($user)
            ->from(route('dashboard.profile'))
            ->delete(route('profile.two-factor.disable'), [
                'current_pin' => self::PIN,
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('dashboard.profile'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('user_pin_credentials', ['user_id' => $user->getKey()]);
        $this->assertNull(session('two_factor_user_id'));
        $this->assertNull(session('two_factor_verified_at'));
        $this->assertNotSame($sessionIdBefore, $this->app['session.store']->getId());
    }

    public function test_an_oauth_only_account_changing_the_pin_needs_only_the_current_pin(): void
    {
        $user = $this->pinUser(password: null);

        $this->actingAsVerifiedPinUser($user)
            ->from(route('dashboard.profile'))
            ->put(route('profile.two-factor.change'), [
                'pin' => self::NEW_PIN,
                'current_pin' => self::PIN,
            ])
            ->assertRedirect(route('dashboard.profile'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEW_PIN, $this->pinHashOf($user)));
    }

    public function test_routes_use_the_spec_uris(): void
    {
        $this->assertSame('/profile/two-factor', parse_url(route('profile.two-factor.enable'), PHP_URL_PATH));
        $this->assertSame('/profile/two-factor', parse_url(route('profile.two-factor.change'), PHP_URL_PATH));
        $this->assertSame('/profile/two-factor', parse_url(route('profile.two-factor.disable'), PHP_URL_PATH));
    }

    /** User aktif dengan PIN tersimpan dan kata sandi lokal opsional. */
    private function pinUser(?string $password = self::PASSWORD): User
    {
        $user = User::factory()->create([
            'password' => $password === null ? null : Hash::make($password),
        ]);

        app(TwoFactorPinService::class)->enable($user, self::PIN);

        return $user;
    }

    /** User ber-PIN yang belum melewati tantangan pada sesi ini. */
    private function actingAsPinUser(User $user): static
    {
        // `actingAs` menyuntik instance tanpa subquery provider; atribut turunan diisi eksplisit
        // dan ditandai tidak kotor agar penyimpanan model tidak menulis kolom turunan.
        $user->forceFill(['has_pin_2fa' => true])->syncOriginalAttribute('has_pin_2fa');

        return $this->actingAs($user);
    }

    /** User yang sudah melewati gerbang faktor kedua pada sesi ini. */
    private function actingAsVerifiedPinUser(User $user): static
    {
        return $this->actingAsPinUser($user)->withSession([
            'two_factor_user_id' => $user->getKey(),
            'two_factor_verified_at' => now()->toIso8601String(),
        ]);
    }

    /** Hash PIN tersimpan milik user. */
    private function pinHashOf(User $user): string
    {
        $hash = $user->newQuery()->getConnection()
            ->table('user_pin_credentials')
            ->where('user_id', $user->getKey())
            ->value('pin_hash');

        $this->assertIsString($hash);

        return $hash;
    }
}

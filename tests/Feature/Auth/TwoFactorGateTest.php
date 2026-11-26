<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\TwoFactorPinService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Gerbang faktor kedua pada grup middleware `web` (spec §6.3, plan Task 5).
 */
class TwoFactorGateTest extends TestCase
{
    use RefreshDatabase;

    /** PIN uji untuk akun yang mengaktifkan faktor kedua. */
    private const PIN = '123456';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_the_provider_computes_has_pin_2fa_from_the_credential_table(): void
    {
        $withoutPin = User::factory()->create();
        $withPin = User::factory()->create();
        app(TwoFactorPinService::class)->enable($withPin, self::PIN);

        $provider = Auth::guard('web')->getProvider();

        $this->assertFalse((bool) $provider->retrieveById($withoutPin->getKey())?->has_pin_2fa);
        $this->assertTrue((bool) $provider->retrieveById($withPin->getKey())?->has_pin_2fa);

        $withPin->setRememberToken('token-uji');
        $withPin->save();

        $remembered = $provider->retrieveByToken($withPin->getKey(), 'token-uji');
        $this->assertNotNull($remembered);
        $this->assertTrue((bool) $remembered->has_pin_2fa);
    }

    public function test_a_pin_user_reaches_the_challenge_before_any_gated_page(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)
            ->get(route('dashboard.profile'))
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_a_real_password_login_also_lands_on_the_challenge(): void
    {
        $user = $this->pinUser();

        $this->post('/auth/login', [
            'email' => $user->email,
            'password' => 'rahasia-uji',
        ])->assertRedirect();

        // Guard menyimpan instance hasil login; paksa resolusi ulang dari sesi seperti request nyata.
        $this->app['auth']->forgetGuards();

        $this->get(route('dashboard.profile'))->assertRedirect(route('two-factor.challenge'));
    }

    public function test_the_gate_remembers_the_original_url_as_intended(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)->get(route('dashboard.profile'));

        $this->assertSame(route('dashboard.profile'), session('url.intended'));
    }

    public function test_a_guest_is_never_sent_to_the_challenge(): void
    {
        $this->get(route('dashboard.profile'))->assertRedirect(route('auth.login'));
    }

    public function test_a_user_without_a_pin_is_never_gated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard.profile'))->assertOk();
    }

    public function test_allowlisted_routes_stay_reachable_without_the_marker(): void
    {
        $user = User::factory()->unverified()->create();
        app(TwoFactorPinService::class)->enable($user, self::PIN);

        $this->actingAsPinUser($user)->get(route('two-factor.challenge'))->assertOk();
        $this->actingAsPinUser($user)->get(route('verification.notice'))->assertOk();
        $this->assertFalse((bool) session('two_factor_user_id'));

        $this->actingAsPinUser($user)->post(route('auth.logout'))->assertRedirect(route('home'));
    }

    public function test_a_public_web_route_is_not_gated(): void
    {
        $user = $this->pinUser();

        // Route `home` hanya memakai grup `web`, tanpa middleware `auth`: gerbang tidak boleh ikut campur.
        $this->actingAsPinUser($user)->get(route('home'))->assertOk();
    }

    public function test_an_unknown_path_still_returns_not_found(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)->get('/path-yang-tidak-terdaftar')->assertNotFound();
    }

    public function test_a_route_with_auth_middleware_still_redirects_to_the_challenge(): void
    {
        $user = $this->pinUser();

        $gatedRoute = Route::getRoutes()->getByName('dashboard.profile');
        $this->assertNotNull($gatedRoute);
        $this->assertContains('auth', $gatedRoute->gatherMiddleware());

        $this->actingAsPinUser($user)
            ->get(route('dashboard.profile'))
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_a_marker_belonging_to_another_user_is_ignored(): void
    {
        $user = $this->pinUser();
        $other = User::factory()->create();

        $this->actingAsPinUser($user)
            ->withSession([
                'two_factor_user_id' => $other->getKey(),
                'two_factor_verified_at' => now()->toIso8601String(),
            ])
            ->get(route('dashboard.profile'))
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_markers_for_the_current_user_unblock_the_gated_page(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)
            ->withSession([
                'two_factor_user_id' => $user->getKey(),
                'two_factor_verified_at' => now()->toIso8601String(),
            ])
            ->get(route('dashboard.profile'))
            ->assertOk();
    }

    public function test_the_allowlist_is_route_name_based(): void
    {
        $this->assertSame(
            ['two-factor.*', 'auth.logout', 'verification.*'],
            \App\Http\Middleware\EnsureTwoFactorVerified::ALLOWED_ROUTE_NAMES,
        );
    }

    /** User aktif dengan kredensial PIN tersimpan. */
    private function pinUser(): User
    {
        $user = User::factory()->create(['password' => Hash::make('rahasia-uji')]);
        app(TwoFactorPinService::class)->enable($user, self::PIN);

        return $user;
    }

    /**
     *
     * `actingAs` menyuntik instance user tanpa subquery provider, jadi atribut turunan
     * `has_pin_2fa` diisi eksplisit; jalur subquery asli diuji di test pertama.
     *
     */
    private function actingAsPinUser(User $user): static
    {
        $user->forceFill(['has_pin_2fa' => true])->syncOriginalAttribute('has_pin_2fa');

        return $this->actingAs($user);
    }
}

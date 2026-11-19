<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\TwoFactorPinService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Halaman tantangan PIN, verifikasi, dan pemulihan lewat OTP email (spec §6.3, plan Task 6).
 */
class TwoFactorChallengeFlowTest extends TestCase
{
    use RefreshDatabase;

    /** PIN awal akun uji. */
    private const PIN = '123456';

    /** PIN pengganti pada alur pemulihan. */
    private const NEW_PIN = '654321';

    protected function setUp(): void
    {
        parent::setUp();

        // Bucket limiter dan cache kode lokal harus mulai dari nol di tiap test.
        Cache::flush();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login_on_every_challenge_route(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('auth.login'));
        $this->post(route('two-factor.verify'), ['pin' => self::PIN])->assertRedirect(route('auth.login'));
        $this->post(route('two-factor.recovery.send'))->assertRedirect(route('auth.login'));
        $this->post(route('two-factor.recovery.confirm'), ['code' => '123456'])->assertRedirect(route('auth.login'));
        $this->post(route('two-factor.recovery.pin'), ['pin' => self::NEW_PIN])->assertRedirect(route('auth.login'));
    }

    public function test_a_user_without_a_pin_is_sent_away_from_the_challenge(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('two-factor.challenge'))
            ->assertRedirect(route('dashboard.user.events'));
    }

    public function test_the_challenge_page_exposes_the_pin_status(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)
            ->get(route('two-factor.challenge'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Halaman Vue milik Task 7; test ini hanya mengunci kontrak props.
                ->component('Auth/TwoFactorChallenge', false)
                ->where('status.enabled', true)
                ->where('status.attemptsRemaining', TwoFactorPinService::MAX_FAILED_ATTEMPTS)
                ->where('status.lockedUntil', null));
    }

    public function test_the_challenge_page_skips_users_already_verified_in_the_session(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)
            ->withSession([
                'two_factor_user_id' => $user->getKey(),
                'two_factor_verified_at' => now()->toIso8601String(),
            ])
            ->get(route('two-factor.challenge'))
            ->assertRedirect(route('dashboard.user.events'));
    }

    public function test_a_correct_pin_regenerates_the_session_and_marks_the_factor_verified(): void
    {
        $user = $this->pinUser();
        $this->actingAsPinUser($user)->get(route('two-factor.challenge'))->assertOk();

        $sessionIdBefore = $this->app['session.store']->getId();

        $this->actingAsPinUser($user)
            ->withSession(['url.intended' => route('dashboard.profile')])
            ->post(route('two-factor.verify'), ['pin' => self::PIN])
            ->assertRedirect(route('dashboard.profile'));

        $this->assertSame($user->getKey(), session('two_factor_user_id'));
        $this->assertNotNull(session('two_factor_verified_at'));
        $this->assertNotSame($sessionIdBefore, $this->app['session.store']->getId());
    }

    public function test_a_correct_pin_without_an_intended_url_lands_on_the_member_portal(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)
            ->post(route('two-factor.verify'), ['pin' => self::PIN])
            ->assertRedirect(route('dashboard.user.events'));
    }

    public function test_a_wrong_pin_counts_the_attempt_and_returns_an_error(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)
            ->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['pin' => '000000'])
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHasErrors('pin');

        $this->assertDatabaseHas('user_pin_credentials', [
            'user_id' => $user->getKey(),
            'failed_attempts' => 1,
        ]);
        $this->assertNull(session('two_factor_user_id'));
    }

    public function test_the_challenge_rejects_a_pin_with_an_invalid_format(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)
            ->post(route('two-factor.verify'), ['pin' => 'abc'])
            ->assertSessionHasErrors('pin');

        $this->assertDatabaseHas('user_pin_credentials', [
            'user_id' => $user->getKey(),
            'failed_attempts' => 0,
        ]);
    }

    public function test_a_locked_account_is_told_to_wait_even_with_the_right_pin(): void
    {
        $user = $this->pinUser();
        $this->lockAccount($user);

        $this->actingAsPinUser($user)
            ->post(route('two-factor.verify'), ['pin' => self::PIN])
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHasErrors('pin');

        $this->assertNull(session('two_factor_user_id'));
    }

    public function test_the_verify_endpoint_is_throttled_per_user(): void
    {
        $user = $this->pinUser();
        $this->lockAccount($user);

        for ($attempt = 1; $attempt <= (int) config('auth_throttle.two_factor_verify.max_attempts'); $attempt++) {
            $this->actingAsPinUser($user)
                ->post(route('two-factor.verify'), ['pin' => '000000'])
                ->assertRedirect(route('two-factor.challenge'));
        }

        $this->actingAsPinUser($user)
            ->post(route('two-factor.verify'), ['pin' => '000000'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_recovery_confirms_the_email_before_a_new_pin_is_required(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)
            ->post(route('two-factor.recovery.send'))
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->getKey(),
            'purpose' => EmailVerificationService::PURPOSE_TWO_FACTOR_RECOVERY,
        ]);

        $this->actingAsPinUser($user)
            ->post(route('two-factor.recovery.confirm'), ['code' => $this->activeCode($user)])
            ->assertRedirect(route('two-factor.challenge'));

        // OTP hanya membuktikan kendali email: faktor kedua BELUM terpenuhi.
        $this->assertNull(session('two_factor_user_id'));
        $this->assertSame($user->getKey(), session('two_factor_recovery_user_id'));
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->getKey()]);

        $this->actingAsPinUser($user)
            ->get(route('dashboard.profile'))
            ->assertRedirect(route('two-factor.challenge'));

        $this->actingAsPinUser($user)
            ->post(route('two-factor.recovery.pin'), ['pin' => self::NEW_PIN])
            ->assertRedirect(route('dashboard.profile'));

        $this->assertSame($user->getKey(), session('two_factor_user_id'));
        $this->assertNotNull(session('two_factor_verified_at'));
        $this->assertNull(session('two_factor_recovery_user_id'));

        $credential = $user->newQuery()->getConnection()
            ->table('user_pin_credentials')
            ->where('user_id', $user->getKey())
            ->first();
        $this->assertNotNull($credential);
        $this->assertTrue(Hash::check(self::NEW_PIN, (string) $credential->pin_hash));
        $this->assertFalse(Hash::check(self::PIN, (string) $credential->pin_hash));
    }

    public function test_the_new_pin_from_recovery_is_the_one_required_at_login(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)->post(route('two-factor.recovery.send'));
        $this->actingAsPinUser($user)
            ->post(route('two-factor.recovery.confirm'), ['code' => $this->activeCode($user)]);
        $this->actingAsPinUser($user)
            ->post(route('two-factor.recovery.pin'), ['pin' => self::NEW_PIN])
            ->assertRedirect(route('dashboard.user.events'));

        // Sesi login baru: penanda faktor kedua dari alur pemulihan tidak boleh terbawa.
        $this->flushSession();

        $this->actingAsPinUser($user)
            ->post(route('two-factor.verify'), ['pin' => self::PIN])
            ->assertSessionHasErrors('pin');

        $this->actingAsPinUser($user)
            ->post(route('two-factor.verify'), ['pin' => self::NEW_PIN])
            ->assertRedirect(route('dashboard.user.events'));

        $this->assertSame($user->getKey(), session('two_factor_user_id'));
    }

    public function test_the_new_pin_step_requires_a_confirmed_otp(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)
            ->post(route('two-factor.recovery.pin'), ['pin' => self::NEW_PIN])
            ->assertForbidden();

        $this->assertFalse(Hash::check(self::NEW_PIN, $this->pinHashOf($user)));
    }

    public function test_a_wrong_recovery_code_counts_the_attempt(): void
    {
        $user = $this->pinUser();

        $this->actingAsPinUser($user)->post(route('two-factor.recovery.send'));

        $this->actingAsPinUser($user)
            ->post(route('two-factor.recovery.confirm'), ['code' => $this->wrongCode($this->activeCode($user))])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->getKey(),
            'purpose' => EmailVerificationService::PURPOSE_TWO_FACTOR_RECOVERY,
            'attempts' => 1,
        ]);
        $this->assertNull(session('two_factor_recovery_user_id'));
    }

    public function test_the_recovery_send_endpoint_is_throttled_per_user(): void
    {
        $user = $this->pinUser();

        for ($attempt = 1; $attempt <= (int) config('auth_throttle.two_factor_recovery.max_attempts'); $attempt++) {
            $this->actingAsPinUser($user)
                ->post(route('two-factor.recovery.send'))
                ->assertRedirect(route('two-factor.challenge'));
        }

        $this->actingAsPinUser($user)
            ->post(route('two-factor.recovery.send'))
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_routes_use_the_spec_uris(): void
    {
        $this->assertSame('/auth/two-factor', parse_url(route('two-factor.challenge'), PHP_URL_PATH));
        $this->assertSame('/auth/two-factor', parse_url(route('two-factor.verify'), PHP_URL_PATH));
        $this->assertSame('/auth/two-factor/recovery', parse_url(route('two-factor.recovery.send'), PHP_URL_PATH));
        $this->assertSame('/auth/two-factor/recovery/confirm', parse_url(route('two-factor.recovery.confirm'), PHP_URL_PATH));
        $this->assertSame('/auth/two-factor/recovery/pin', parse_url(route('two-factor.recovery.pin'), PHP_URL_PATH));
    }

    /** User aktif dengan PIN tersimpan. */
    private function pinUser(): User
    {
        $user = User::factory()->create();

        app(TwoFactorPinService::class)->enable($user, self::PIN);

        return $user;
    }

    /**
     *
     * `actingAs` menyuntik instance user tanpa subquery provider, jadi atribut turunan
     * `has_pin_2fa` diisi eksplisit; jalur subquery asli diuji di TwoFactorGateTest.
     *
     */
    private function actingAsPinUser(User $user): static
    {
        $user->forceFill(['has_pin_2fa' => true])->syncOriginalAttribute('has_pin_2fa');

        return $this->actingAs($user);
    }

    /** Kunci akun lewat lima percobaan salah pada service. */
    private function lockAccount(User $user): void
    {
        $service = app(TwoFactorPinService::class);

        for ($attempt = 1; $attempt <= TwoFactorPinService::MAX_FAILED_ATTEMPTS; $attempt++) {
            $service->verify($user, '000000');
        }
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

    /** Kode OTP aktif milik user; job pengirim berjalan sebaris di driver sync. */
    private function activeCode(User $user): string
    {
        $code = app(EmailVerificationService::class)->currentCode($user);
        $this->assertIsString($code, 'Kode OTP aktif tidak ditemukan.');

        return $code;
    }

    /** Tebakan yang dijamin berbeda dari kode aktif agar test tidak flaky. */
    private function wrongCode(string $actual): string
    {
        return $actual === '000000' ? '111111' : '000000';
    }
}

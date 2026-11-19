<?php

namespace Tests\Feature\Auth;

use App\Enums\TwoFactorVerifyResult;
use App\Models\User;
use App\Models\UserPinCredential;
use App\Services\Auth\TwoFactorPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Aturan penyimpanan, lockout, dan status PIN faktor kedua (spec §6.2, §6.5, plan Task 4).
 */
class TwoFactorPinServiceTest extends TestCase
{
    use RefreshDatabase;

    /** PIN uji; semua test memakai panjang dari config agar konsisten dengan validasi. */
    private const PIN = '123456';

    private TwoFactorPinService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TwoFactorPinService::class);
    }

    public function test_enable_stores_a_hash_and_never_the_plain_pin(): void
    {
        $user = User::factory()->create();

        $this->service->enable($user, self::PIN);

        $credential = $this->credentialOf($user);

        $this->assertNotSame(self::PIN, (string) $credential->pin_hash);
        $this->assertTrue(Hash::check(self::PIN, (string) $credential->pin_hash));
        $this->assertSame(0, $credential->failed_attempts);
        $this->assertNull($credential->locked_until);
        $this->assertNotNull($credential->enabled_at);
    }

    public function test_the_pin_hash_never_leaks_through_serialization(): void
    {
        $user = User::factory()->create();
        $this->service->enable($user, self::PIN);

        $payload = (string) json_encode($this->credentialOf($user)->toArray());

        $this->assertStringNotContainsString(self::PIN, $payload);
        $this->assertStringNotContainsString((string) $this->credentialOf($user)->pin_hash, $payload);
    }

    public function test_verify_without_a_credential_is_not_enabled(): void
    {
        $this->assertSame(
            TwoFactorVerifyResult::NotEnabled,
            $this->service->verify(User::factory()->create(), self::PIN),
        );
    }

    public function test_verify_with_the_correct_pin_resets_counters_and_stamps_last_verified_at(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $this->service->enable($user, self::PIN);

        $this->assertSame(TwoFactorVerifyResult::InvalidPin, $this->service->verify($user, '000000'));
        $this->assertSame(TwoFactorVerifyResult::InvalidPin, $this->service->verify($user, '000000'));
        $this->assertSame(2, $this->credentialOf($user)->failed_attempts);

        $this->assertSame(TwoFactorVerifyResult::Verified, $this->service->verify($user, self::PIN));

        $credential = $this->credentialOf($user);
        $this->assertSame(0, $credential->failed_attempts);
        $this->assertNull($credential->locked_until);
        // Timestamp database tersimpan per detik, jadi dibandingkan lewat epoch detik.
        $this->assertSame(now()->getTimestamp(), $credential->last_verified_at?->getTimestamp());
    }

    public function test_five_consecutive_failures_lock_the_account_for_the_configured_window(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $this->service->enable($user, self::PIN);

        for ($attempt = 1; $attempt < 5; $attempt++) {
            $this->assertSame(TwoFactorVerifyResult::InvalidPin, $this->service->verify($user, '000000'));
        }

        $this->assertSame(TwoFactorVerifyResult::Locked, $this->service->verify($user, '000000'));

        $locked = $this->credentialOf($user);
        $this->assertSame(5, $locked->failed_attempts);
        $this->assertSame(
            now()->addMinutes((int) config('otp.lock_minutes'))->getTimestamp(),
            $locked->locked_until?->getTimestamp(),
        );
    }

    public function test_a_locked_account_rejects_even_the_correct_pin(): void
    {
        $user = User::factory()->create();
        $this->service->enable($user, self::PIN);
        $this->lockAccount($user);

        $this->assertSame(TwoFactorVerifyResult::Locked, $this->service->verify($user, self::PIN));
    }

    public function test_the_lock_lifts_after_the_configured_window(): void
    {
        $user = User::factory()->create();
        $this->service->enable($user, self::PIN);
        $this->lockAccount($user);

        $this->travel((int) config('otp.lock_minutes') + 1)->minutes();

        $this->assertSame(TwoFactorVerifyResult::Verified, $this->service->verify($user, self::PIN));

        $credential = $this->credentialOf($user);
        $this->assertSame(0, $credential->failed_attempts);
        $this->assertNull($credential->locked_until);
    }

    public function test_disable_removes_the_credential(): void
    {
        $user = User::factory()->create();
        $this->service->enable($user, self::PIN);

        $this->service->disable($user);

        $this->assertDatabaseMissing('user_pin_credentials', ['user_id' => $user->getKey()]);
        $this->assertSame(TwoFactorVerifyResult::NotEnabled, $this->service->verify($user, self::PIN));
    }

    public function test_change_replaces_the_hash_and_clears_the_lockout(): void
    {
        $user = User::factory()->create();
        $this->service->enable($user, self::PIN);
        $this->lockAccount($user);

        $this->service->change($user, '654321');

        $credential = $this->credentialOf($user);
        $this->assertTrue(Hash::check('654321', (string) $credential->pin_hash));
        $this->assertFalse(Hash::check(self::PIN, (string) $credential->pin_hash));
        $this->assertSame(0, $credential->failed_attempts);
        $this->assertNull($credential->locked_until);

        $this->assertSame(TwoFactorVerifyResult::Verified, $this->service->verify($user, '654321'));
        $this->assertSame(TwoFactorVerifyResult::InvalidPin, $this->service->verify($user, self::PIN));
    }

    public function test_status_reports_enabled_attempts_and_lock(): void
    {
        $user = User::factory()->create();

        $disabled = $this->service->status($user);
        $this->assertFalse($disabled['enabled']);
        $this->assertSame(TwoFactorPinService::MAX_FAILED_ATTEMPTS, $disabled['attemptsRemaining']);
        $this->assertNull($disabled['lockedUntil']);

        $this->service->enable($user, self::PIN);

        $enabled = $this->service->status($user);
        $this->assertTrue($enabled['enabled']);
        $this->assertSame(TwoFactorPinService::MAX_FAILED_ATTEMPTS, $enabled['attemptsRemaining']);
        $this->assertNull($enabled['lockedUntil']);

        $this->service->verify($user, '000000');
        $this->service->verify($user, '000000');

        $this->assertSame(TwoFactorPinService::MAX_FAILED_ATTEMPTS - 2, $this->service->status($user)['attemptsRemaining']);

        $this->lockAccount($user);

        $locked = $this->service->status($user);
        $this->assertTrue($locked['enabled']);
        $this->assertSame(0, $locked['attemptsRemaining']);
        $this->assertNotNull($locked['lockedUntil']);
    }

    /** Kunci akun lewat lima percobaan salah (jalur publik service, bukan state langsung). */
    private function lockAccount(User $user): void
    {
        for ($attempt = 1; $attempt <= TwoFactorPinService::MAX_FAILED_ATTEMPTS; $attempt++) {
            $this->service->verify($user, '000000');
        }

        $this->assertNotNull($this->credentialOf($user)->locked_until);
    }

    /** Baris kredensial PIN milik user; gagal bila belum dibuat. */
    private function credentialOf(User $user): UserPinCredential
    {
        $credential = UserPinCredential::query()->where('user_id', $user->getKey())->first();
        $this->assertNotNull($credential, 'Kredensial PIN tidak ditemukan.');

        return $credential;
    }
}

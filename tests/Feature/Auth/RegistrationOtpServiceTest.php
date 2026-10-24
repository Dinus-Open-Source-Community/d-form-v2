<?php

namespace Tests\Feature\Auth;

use App\Enums\OtpOutcome;
use App\Jobs\SendRegistrationOtpJob;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Auth\RegistrationOtpService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegistrationOtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private RegistrationOtpService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Queue::fake();
        $this->service = app(RegistrationOtpService::class);
    }

    public function test_third_wrong_code_invalidates_otp(): void
    {
        $this->service->start($this->registrationData('otp@example.test'));
        $code = $this->activeCode('otp@example.test');

        $this->assertSame(OtpOutcome::InvalidCode, $this->service->verify('otp@example.test', '000000'));
        $this->assertSame(OtpOutcome::InvalidCode, $this->service->verify('otp@example.test', '000001'));
        $this->assertSame(OtpOutcome::InvalidCode, $this->service->verify('otp@example.test', '000002'));
        $this->assertSame(OtpOutcome::Expired, $this->service->verify('otp@example.test', $code));
    }

    public function test_verify_success_creates_verified_member_and_deletes_pending(): void
    {
        $this->service->start($this->registrationData('verif@example.test'));

        $outcome = $this->service->verify('verif@example.test', $this->activeCode('verif@example.test'));

        $this->assertSame(OtpOutcome::Verified, $outcome);
        $this->assertDatabaseMissing('pending_registrations', ['email' => 'verif@example.test']);
        $this->assertGuest();

        $user = User::query()->where('email', 'verif@example.test')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('member'));
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        Queue::assertPushed(SendRegistrationOtpJob::class);
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->service->start($this->registrationData('expired@example.test'));
        $code = $this->activeCode('expired@example.test');

        $this->travel(11)->minutes();

        $this->assertSame(OtpOutcome::Expired, $this->service->verify('expired@example.test', $code));
        $this->assertDatabaseMissing('users', ['email' => 'expired@example.test']);
    }

    public function test_quota_locks_after_third_send_and_recovers_after_lock_window(): void
    {
        $this->service->start($this->registrationData('kuota@example.test'));

        $this->travel(61)->seconds();
        $this->assertSame(OtpOutcome::Issued, $this->service->resend('kuota@example.test'));

        $this->travel(61)->seconds();
        $this->assertSame(OtpOutcome::Issued, $this->service->resend('kuota@example.test'));
        $this->assertSame(OtpOutcome::Locked, $this->service->resend('kuota@example.test'));

        $this->travel(16)->minutes();
        $this->assertSame(OtpOutcome::Issued, $this->service->resend('kuota@example.test'));

        $pending = PendingRegistration::query()->where('email', 'kuota@example.test')->first();
        $this->assertNotNull($pending);
        $this->assertSame(1, $pending->otp_sends);
        $this->assertNull($pending->otp_locked_until);
    }

    public function test_resend_during_cooldown_is_locked(): void
    {
        $this->service->start($this->registrationData('cooldown@example.test'));

        $this->travel(10)->seconds();

        $this->assertSame(OtpOutcome::Locked, $this->service->resend('cooldown@example.test'));
    }

    public function test_resume_with_wrong_password_leaves_pending_untouched(): void
    {
        $this->service->start($this->registrationData('resume@example.test'));
        $before = $this->pendingSnapshot('resume@example.test');

        $outcome = $this->service->start($this->registrationData('resume@example.test', 'password-lain'));

        $this->assertSame(OtpOutcome::PasswordMismatch, $outcome);
        $this->assertSame($before, $this->pendingSnapshot('resume@example.test'));
    }

    public function test_resume_with_matching_password_keeps_identity_and_uses_a_send(): void
    {
        $this->service->start($this->registrationData('ulang@example.test'));

        $outcome = $this->service->start([
            'name' => 'Nama Lain',
            'email' => 'ulang@example.test',
            'password' => 'rahasia123',
        ]);

        $this->assertSame(OtpOutcome::Issued, $outcome);
        $this->assertSame(1, PendingRegistration::query()->where('email', 'ulang@example.test')->count());

        $pending = PendingRegistration::query()->where('email', 'ulang@example.test')->first();
        $this->assertNotNull($pending);
        $this->assertSame('Uji', $pending->name);
        $this->assertSame(2, $pending->otp_sends);
        Queue::assertPushed(SendRegistrationOtpJob::class, 2);
    }

    public function test_unknown_email_is_missing(): void
    {
        $this->assertSame(OtpOutcome::Missing, $this->service->verify('hilang@example.test', '123456'));
        $this->assertSame(OtpOutcome::Missing, $this->service->resend('hilang@example.test'));
    }

    public function test_status_reports_remaining_quota_and_deadlines(): void
    {
        $this->service->start($this->registrationData('status@example.test'));

        $status = $this->service->status($this->pending('status@example.test'));

        $this->assertSame('status@example.test', $status['email']);
        $this->assertSame(2, $status['sendsRemaining']);
        $this->assertSame(3, $status['attemptsRemaining']);
        $this->assertNotNull($status['expiresAt']);
        $this->assertNull($status['lockedUntil']);
        $this->assertNotNull($status['resendAvailableAt']);
        $this->assertIsString($status['serverNow']);
    }

    public function test_status_after_three_wrong_attempts_is_hungus(): void
    {
        $this->service->start($this->registrationData('hangus@example.test'));

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->service->verify('hangus@example.test', '000000');
        }

        $status = $this->service->status($this->pending('hangus@example.test'));

        $this->assertNull($status['expiresAt']);
        $this->assertSame(0, $status['attemptsRemaining']);
    }

    public function test_cancel_deletes_pending(): void
    {
        $this->service->start($this->registrationData('batal@example.test'));

        $this->service->cancel('batal@example.test');

        $this->assertDatabaseMissing('pending_registrations', ['email' => 'batal@example.test']);
        $this->assertNull($this->service->currentCode('batal@example.test'));
    }

    /** Data registrasi minimal yang dipakai seluruh skenario. */
    private function registrationData(string $email, string $password = 'rahasia123'): array
    {
        return ['name' => 'Uji', 'email' => $email, 'password' => $password];
    }

    /** Kode aktif milik pending; gagal bila belum pernah diterbitkan. */
    private function activeCode(string $email): string
    {
        $code = $this->service->currentCode($email);
        $this->assertNotNull($code);

        return $code;
    }

    /** Baris pending untuk email tertentu; gagal bila tidak ada. */
    private function pending(string $email): PendingRegistration
    {
        $pending = PendingRegistration::query()->where('email', $email)->first();
        $this->assertNotNull($pending);

        return $pending;
    }

    /** Potret kolom yang tidak boleh berubah saat resume dengan kata sandi salah. */
    private function pendingSnapshot(string $email): array
    {
        $pending = $this->pending($email);

        return [
            'name' => $pending->name,
            'password' => $pending->password,
            'otp_code' => $pending->otp_code,
            'otp_expires_at' => $pending->otp_expires_at?->toIso8601String(),
            'otp_attempts' => $pending->otp_attempts,
            'otp_sends' => $pending->otp_sends,
            'otp_last_sent_at' => $pending->otp_last_sent_at?->toIso8601String(),
            'otp_locked_until' => $pending->otp_locked_until?->toIso8601String(),
        ];
    }
}

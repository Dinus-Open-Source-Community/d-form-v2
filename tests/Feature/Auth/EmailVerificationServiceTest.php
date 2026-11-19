<?php

namespace Tests\Feature\Auth;

use App\Enums\OtpOutcome;
use App\Jobs\SendEmailVerificationOtpJob;
use App\Mail\EmailVerificationOtpMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Support\Otp\OtpCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Aturan waktu dan kuota kode verifikasi email (spec §2, §6.2, plan Task 2).
 * Antrean test memakai driver sync, jadi job pengirim berjalan sebaris dan hash
 * kode tersimpan sebelum tiap assertion yang membutuhkannya.
 */
class EmailVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Bucket limiter dan cache kode lokal tidak boleh menyeberang antar test.
        Cache::flush();
        $this->service = app(EmailVerificationService::class);
    }

    public function test_issue_creates_one_challenge_and_dispatches_the_email_job(): void
    {
        Queue::fake();
        $user = $this->unverifiedUser();

        $this->assertSame(OtpOutcome::Issued, $this->service->issue($user));

        $challenge = $this->challengeOf($user);
        $this->assertSame(1, $challenge->sends);
        $this->assertSame(0, $challenge->attempts);
        $this->assertNull($challenge->locked_until);
        $this->assertNull($challenge->code_hash);
        $this->assertTrue(
            $challenge->expires_at?->equalTo($challenge->last_sent_at?->copy()->addMinutes((int) config('otp.ttl_minutes'))) ?? false,
        );

        Queue::assertPushed(SendEmailVerificationOtpJob::class, function (SendEmailVerificationOtpJob $job) use ($challenge, $user): bool {
            return $job->userId === $user->getKey()
                && $job->sentAt === $challenge->last_sent_at?->toIso8601String()
                && ! property_exists($job, 'code');
        });
    }

    public function test_issue_stores_only_a_hmac_of_the_code(): void
    {
        $user = $this->unverifiedUser();

        $this->service->issue($user);

        $challenge = $this->challengeOf($user);
        $code = $this->activeCode($user);
        $hash = (string) $challenge->code_hash;

        $this->assertSame(64, strlen($hash));
        $this->assertNotSame($code, $hash);
        $this->assertTrue(OtpCode::matches($code, $hash));
        $this->assertStringNotContainsString($code, (string) json_encode($challenge->getAttributes()));
    }

    public function test_issue_is_a_no_op_for_an_already_verified_user(): void
    {
        $user = User::factory()->create();

        $this->assertSame(OtpOutcome::Verified, $this->service->issue($user));
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->getKey()]);
    }

    public function test_issue_during_resend_cooldown_is_locked(): void
    {
        $user = $this->unverifiedUser();
        $this->service->issue($user);

        $this->travel(10)->seconds();

        $this->assertSame(OtpOutcome::Locked, $this->service->issue($user));
        $this->assertSame(1, $this->challengeOf($user)->sends);
    }

    public function test_issue_after_the_cooldown_replaces_the_code(): void
    {
        $user = $this->unverifiedUser();
        $this->service->issue($user);
        $firstHash = (string) $this->challengeOf($user)->code_hash;

        $this->travel((int) config('otp.resend_cooldown_seconds') + 1)->seconds();

        $this->assertSame(OtpOutcome::Issued, $this->service->issue($user));

        $challenge = $this->challengeOf($user);
        $this->assertSame(2, $challenge->sends);
        $this->assertNotSame($firstHash, (string) $challenge->code_hash);
        $this->assertSame(1, EmailVerificationCode::query()->where('user_id', $user->getKey())->count());
    }

    public function test_third_send_locks_for_the_lock_window_and_recovers_afterwards(): void
    {
        $user = $this->unverifiedUser();

        for ($send = 1; $send <= (int) config('otp.max_sends'); $send++) {
            $this->service->issue($user);
            $this->travel((int) config('otp.resend_cooldown_seconds') + 1)->seconds();
        }

        $locked = $this->challengeOf($user);
        $this->assertSame((int) config('otp.max_sends'), $locked->sends);
        $this->assertNotNull($locked->locked_until);
        $this->assertTrue(
            $locked->locked_until?->equalTo($locked->last_sent_at?->copy()->addMinutes((int) config('otp.lock_minutes'))) ?? false,
        );

        $this->assertSame(OtpOutcome::Locked, $this->service->issue($user));

        $this->travel((int) config('otp.lock_minutes') + 1)->minutes();

        $this->assertSame(OtpOutcome::Issued, $this->service->issue($user));

        $recovered = $this->challengeOf($user);
        $this->assertSame(1, $recovered->sends);
        $this->assertNull($recovered->locked_until);
    }

    public function test_a_code_issued_by_the_third_send_is_still_verifiable_while_locked(): void
    {
        $user = $this->unverifiedUser();

        for ($send = 1; $send <= (int) config('otp.max_sends'); $send++) {
            $this->service->issue($user);

            if ($send < (int) config('otp.max_sends')) {
                $this->travel((int) config('otp.resend_cooldown_seconds') + 1)->seconds();
            }
        }

        $this->assertNotNull($this->challengeOf($user)->locked_until);
        $this->assertSame(OtpOutcome::Verified, $this->service->verify($user, $this->activeCode($user)));
    }

    public function test_verify_success_marks_the_email_verified_and_deletes_the_challenge(): void
    {
        $user = $this->unverifiedUser();
        $this->service->issue($user);

        $this->assertSame(OtpOutcome::Verified, $this->service->verify($user, $this->activeCode($user)));

        $this->assertNotNull($user->fresh()?->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->getKey()]);
    }

    public function test_verify_rejects_a_wrong_code_and_counts_the_attempt(): void
    {
        $user = $this->unverifiedUser();
        $this->service->issue($user);

        $this->assertSame(OtpOutcome::InvalidCode, $this->service->verify($user, $this->wrongCode($this->activeCode($user))));

        $challenge = $this->challengeOf($user);
        $this->assertSame(1, $challenge->attempts);
        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_third_wrong_attempt_burns_the_code(): void
    {
        $user = $this->unverifiedUser();
        $this->service->issue($user);
        $wrong = $this->wrongCode($this->activeCode($user));

        for ($attempt = 1; $attempt <= (int) config('otp.max_attempts'); $attempt++) {
            $this->assertSame(OtpOutcome::InvalidCode, $this->service->verify($user, $wrong));
        }

        $challenge = $this->challengeOf($user);
        $this->assertSame((int) config('otp.max_attempts'), $challenge->attempts);
        $this->assertNull($challenge->code_hash);

        $this->assertSame(OtpOutcome::Expired, $this->service->verify($user, $wrong));
        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = $this->unverifiedUser();
        $this->service->issue($user);
        $code = $this->activeCode($user);

        $this->travel((int) config('otp.ttl_minutes') + 1)->minutes();

        $this->assertSame(OtpOutcome::Expired, $this->service->verify($user, $code));
        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_a_code_from_another_user_never_verifies(): void
    {
        $owner = $this->unverifiedUser('pemilik@example.test');
        $other = $this->unverifiedUser('lain@example.test');

        $this->service->issue($owner);
        $this->service->issue($other);

        $ownerCode = $this->activeCode($owner);

        $this->assertSame(OtpOutcome::InvalidCode, $this->service->verify($other, $ownerCode));
        $this->assertSame(1, $this->challengeOf($other)->attempts);
        $this->assertNull($owner->fresh()?->email_verified_at);

        $this->assertSame(OtpOutcome::Verified, $this->service->verify($owner, $ownerCode));
    }

    public function test_verify_without_an_active_challenge_is_missing(): void
    {
        $this->assertSame(OtpOutcome::Missing, $this->service->verify($this->unverifiedUser(), '123456'));
    }

    public function test_job_payload_never_contains_the_code(): void
    {
        $user = $this->unverifiedUser();
        $this->service->issue($user);

        $payload = (string) json_encode(new SendEmailVerificationOtpJob($user->getKey(), now()->toIso8601String()));

        $this->assertStringNotContainsString($this->activeCode($user), $payload);
        $this->assertFalse(property_exists(new SendEmailVerificationOtpJob($user->getKey(), now()->toIso8601String()), 'code'));
    }

    public function test_job_sends_the_code_to_the_verified_address(): void
    {
        Mail::fake();
        $user = $this->unverifiedUser();

        $this->service->issue($user);

        Mail::assertSent(EmailVerificationOtpMail::class, function (EmailVerificationOtpMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->code === $this->activeCode($user)
                && $mail->ttlMinutes === (int) config('otp.ttl_minutes');
        });
    }

    public function test_job_skips_missing_expired_superseded_and_verified_challenges(): void
    {
        Mail::fake();

        // Baris tidak ada.
        (new SendEmailVerificationOtpJob((string) Str::uuid(), now()->toIso8601String()))->handle();

        // Kode kedaluwarsa.
        $expired = $this->unverifiedUser('expired@example.test');
        $expiredChallenge = $this->directChallenge($expired, ['expires_at' => now()->subMinute()]);
        (new SendEmailVerificationOtpJob($expired->getKey(), $this->sentAtOf($expiredChallenge)))->handle();

        // Job sudah disuperseded kiriman yang lebih baru.
        $superseded = $this->unverifiedUser('superseded@example.test');
        $supersededChallenge = $this->directChallenge($superseded);
        $staleSentAt = $this->sentAtOf($supersededChallenge);
        $supersededChallenge->forceFill(['last_sent_at' => now()->addMinute()])->save();
        (new SendEmailVerificationOtpJob($superseded->getKey(), $staleSentAt))->handle();

        // Email sudah terverifikasi sebelum job berjalan.
        $verified = User::factory()->create();
        $verifiedChallenge = $this->directChallenge($verified);
        (new SendEmailVerificationOtpJob($verified->getKey(), $this->sentAtOf($verifiedChallenge)))->handle();

        Mail::assertNothingSent();
    }

    public function test_job_does_not_write_a_hash_when_the_challenge_is_missing(): void
    {
        $user = $this->unverifiedUser();
        $challenge = $this->directChallenge($user, ['code_hash' => null]);

        // User dihapus permanen di antara dispatch dan eksekusi job.
        $userId = $user->getKey();
        $challenge->delete();
        $user->forceDelete();

        (new SendEmailVerificationOtpJob((string) $userId, now()->toIso8601String()))->handle();

        $this->assertDatabaseCount('email_verification_codes', 0);
    }

    public function test_job_writes_the_hash_refreshes_expiry_and_does_not_increment_sends(): void
    {
        Mail::fake();
        $user = $this->unverifiedUser('job-hash@example.test');
        $challenge = $this->directChallenge($user, [
            'code_hash' => null,
            'sends' => 2,
            'expires_at' => now()->addMinute(),
        ]);

        (new SendEmailVerificationOtpJob($user->getKey(), $this->sentAtOf($challenge)))->handle();

        $fresh = $this->challengeOf($user);

        $this->assertSame(2, $fresh->sends);
        $this->assertNotNull($fresh->code_hash);
        $this->assertTrue(OtpCode::matches($this->activeCode($user), (string) $fresh->code_hash));
        $this->assertNotNull($fresh->expires_at);
        $this->assertTrue(
            abs($fresh->expires_at->diffInSeconds(now()->addMinutes((int) config('otp.ttl_minutes')))) <= 2,
        );
    }

    public function test_status_reports_expiry_immediately_after_issue_even_before_the_job_runs(): void
    {
        Queue::fake();
        $user = $this->unverifiedUser();

        $this->assertSame(OtpOutcome::Issued, $this->service->issue($user));
        $this->assertNull($this->challengeOf($user)->code_hash);

        $status = $this->service->status($user);

        $this->assertNotNull($status['expiresAt']);
        $this->assertSame((int) config('otp.max_attempts'), $status['attemptsRemaining']);
        $this->assertSame((int) config('otp.max_sends') - 1, $status['sendsRemaining']);
        $this->assertNotNull($status['resendAvailableAt']);
    }

    public function test_verify_before_the_code_lands_reports_missing_without_spending_an_attempt(): void
    {
        Queue::fake();
        $user = $this->unverifiedUser();

        $this->service->issue($user);
        $this->assertNull($this->challengeOf($user)->code_hash);

        $this->assertSame(OtpOutcome::Missing, $this->service->verify($user, '123456'));
        $this->assertSame(0, $this->challengeOf($user)->attempts);
        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_resend_is_allowed_after_a_stalled_delivery(): void
    {
        Queue::fake();
        $user = $this->unverifiedUser();

        $this->service->issue($user);
        $this->assertNull($this->challengeOf($user)->code_hash);

        $this->travel(EmailVerificationService::DEAD_DELIVERY_RETRY_SECONDS - 1)->seconds();
        $this->assertSame(OtpOutcome::Locked, $this->service->issue($user));

        $this->travel(2)->seconds();
        $this->assertSame(OtpOutcome::Issued, $this->service->issue($user));
        $this->assertSame(2, $this->challengeOf($user)->sends);
    }

    public function test_resend_is_still_blocked_by_the_cooldown_when_the_code_landed(): void
    {
        $user = $this->unverifiedUser();

        $this->service->issue($user);
        $this->assertNotNull($this->challengeOf($user)->code_hash);

        $this->travel(EmailVerificationService::DEAD_DELIVERY_RETRY_SECONDS + 1)->seconds();

        $this->assertSame(OtpOutcome::Locked, $this->service->issue($user));
        $this->assertSame(1, $this->challengeOf($user)->sends);
    }

    public function test_status_reports_counters_and_deadlines(): void
    {
        $user = $this->unverifiedUser();

        $fresh = $this->service->status($user);
        $this->assertNull($fresh['expiresAt']);
        $this->assertSame(0, $fresh['attemptsRemaining']);
        $this->assertSame((int) config('otp.max_sends'), $fresh['sendsRemaining']);
        $this->assertNull($fresh['lockedUntil']);
        $this->assertNull($fresh['resendAvailableAt']);
        $this->assertIsString($fresh['serverNow']);

        $this->service->issue($user);

        $issued = $this->service->status($user);
        $this->assertNotNull($issued['expiresAt']);
        $this->assertSame((int) config('otp.max_attempts'), $issued['attemptsRemaining']);
        $this->assertSame((int) config('otp.max_sends') - 1, $issued['sendsRemaining']);
        $this->assertNull($issued['lockedUntil']);
        $this->assertNotNull($issued['resendAvailableAt']);
    }

    public function test_status_hides_the_code_after_it_is_burned(): void
    {
        $user = $this->unverifiedUser();
        $this->service->issue($user);
        $wrong = $this->wrongCode($this->activeCode($user));

        for ($attempt = 1; $attempt <= (int) config('otp.max_attempts'); $attempt++) {
            $this->service->verify($user, $wrong);
        }

        $status = $this->service->status($user);

        $this->assertNull($status['expiresAt']);
        $this->assertSame(0, $status['attemptsRemaining']);
    }

    public function test_status_of_a_verified_user_has_no_challenge(): void
    {
        $status = $this->service->status(User::factory()->create());

        $this->assertNull($status['expiresAt']);
        $this->assertNull($status['resendAvailableAt']);
        $this->assertSame(0, $status['attemptsRemaining']);
    }

    public function test_command_prints_the_active_code(): void
    {
        $user = $this->unverifiedUser('command@example.test');
        $this->service->issue($user);

        $this->artisan('email:verification-code', ['email' => $user->email])
            ->expectsOutput($this->activeCode($user))
            ->assertSuccessful();
    }

    public function test_command_fails_without_an_active_code(): void
    {
        $user = $this->unverifiedUser('tanpa-kode@example.test');

        $this->artisan('email:verification-code', ['email' => $user->email])->assertExitCode(1);
    }

    public function test_command_is_refused_outside_local_and_testing(): void
    {
        $user = $this->unverifiedUser('production@example.test');
        $this->service->issue($user);
        $code = $this->activeCode($user);

        $this->app['env'] = 'production';

        $this->artisan('email:verification-code', ['email' => $user->email])
            ->doesntExpectOutput($code)
            ->assertExitCode(1);
    }

    /** User dengan email belum terverifikasi. */
    private function unverifiedUser(string $email = 'verifikasi@example.test'): User
    {
        return User::factory()->unverified()->create(['email' => $email]);
    }

    /** Kode aktif dari cache lokal (hanya ditulis job di local/testing). */
    private function activeCode(User $user): string
    {
        $code = $this->service->currentCode($user);
        $this->assertIsString($code, 'Kode verifikasi aktif tidak ditemukan.');

        return $code;
    }

    /** Tebakan yang dijamin berbeda dari kode aktif agar test tidak flaky. */
    private function wrongCode(string $actual): string
    {
        return $actual === '000000' ? '111111' : '000000';
    }

    /** Baris tantangan milik user; gagal bila belum diterbitkan. */
    private function challengeOf(User $user): EmailVerificationCode
    {
        $challenge = EmailVerificationCode::query()->where('user_id', $user->getKey())->first();
        $this->assertNotNull($challenge);

        return $challenge;
    }

    /**
     *
     * Baris tantangan siap pakai untuk menguji job tanpa melewati service.
     *
     * @param  array<string, mixed>  $overrides
     *
     */
    private function directChallenge(User $user, array $overrides = []): EmailVerificationCode
    {
        return EmailVerificationCode::create(array_merge([
            'user_id' => $user->getKey(),
            'code_hash' => OtpCode::hash('123456'),
            'expires_at' => now()->addMinutes((int) config('otp.ttl_minutes')),
            'attempts' => 0,
            'sends' => 1,
            'last_sent_at' => now(),
        ], $overrides));
    }

    /** `sentAt` yang dikirim service saat dispatch: ISO8601 dari `last_sent_at`. */
    private function sentAtOf(EmailVerificationCode $challenge): string
    {
        $this->assertNotNull($challenge->last_sent_at);

        return $challenge->last_sent_at->toIso8601String();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendRegistrationOtpJob;
use App\Mail\RegistrationOtpMail;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SendRegistrationOtpJobTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'otp-queue@example.test';

    private const CODE = '123456';

    public function test_job_sends_otp_email(): void
    {
        Mail::fake();
        $pending = $this->makePending();

        (new SendRegistrationOtpJob($pending->id, $this->sentAtOf($pending)))->handle();

        Mail::assertSent(RegistrationOtpMail::class, function (RegistrationOtpMail $mail) use ($pending): bool {
            return $mail->hasTo($pending->email)
                && $mail->code === self::CODE
                && $mail->ttlMinutes === (int) config('otp.ttl_minutes');
        });
    }

    public function test_job_skips_when_a_newer_code_was_issued_after_dispatch(): void
    {
        Mail::fake();
        $pending = $this->makePending();
        $sentAt = $this->sentAtOf($pending);

        $pending->forceFill(['otp_last_sent_at' => now()->addMinute()])->save();

        (new SendRegistrationOtpJob($pending->id, $sentAt))->handle();

        Mail::assertNothingSent();
    }

    public function test_job_skips_when_pending_is_missing(): void
    {
        Mail::fake();

        (new SendRegistrationOtpJob((string) Str::uuid(), now()->toIso8601String()))->handle();

        Mail::assertNothingSent();
    }

    public function test_job_skips_when_code_is_expired(): void
    {
        Mail::fake();
        $pending = $this->makePending(['otp_expires_at' => now()->subMinute()]);

        (new SendRegistrationOtpJob($pending->id, $this->sentAtOf($pending)))->handle();

        Mail::assertNothingSent();
    }

    public function test_job_skips_when_code_is_null(): void
    {
        Mail::fake();
        $pending = $this->makePending(['otp_code' => null]);

        (new SendRegistrationOtpJob($pending->id, $this->sentAtOf($pending)))->handle();

        Mail::assertNothingSent();
    }

    public function test_job_skips_when_code_is_empty_string(): void
    {
        Mail::fake();
        $pending = $this->makePending(['otp_code' => '']);

        (new SendRegistrationOtpJob($pending->id, $this->sentAtOf($pending)))->handle();

        Mail::assertNothingSent();
    }

    public function test_job_skips_when_expiry_is_missing(): void
    {
        Mail::fake();
        $pending = $this->makePending(['otp_expires_at' => null]);

        (new SendRegistrationOtpJob($pending->id, $this->sentAtOf($pending)))->handle();

        Mail::assertNothingSent();
    }

    public function test_job_skips_when_user_already_registered(): void
    {
        Mail::fake();
        $pending = $this->makePending();
        User::create([
            'name' => 'Sudah Ada',
            'email' => self::EMAIL,
            'password' => 'rahasia123',
        ]);

        (new SendRegistrationOtpJob($pending->id, $this->sentAtOf($pending)))->handle();

        Mail::assertNothingSent();
    }

    public function test_mailable_copy_mentions_code_ttl_and_ignore_instruction(): void
    {
        $ttlMinutes = (int) config('otp.ttl_minutes');
        $mail = new RegistrationOtpMail('Uji', self::CODE, $ttlMinutes);

        $html = $mail->render();

        $this->assertStringContainsString(self::CODE, $html);
        $this->assertStringContainsString($ttlMinutes.' menit', $html);
        $this->assertStringContainsString('abaikan', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('<?php', $html);

        $text = view('mail.registration-otp-text', [
            'recipientName' => 'Uji',
            'code' => self::CODE,
            'ttlMinutes' => $ttlMinutes,
        ])->render();

        $this->assertStringContainsString(self::CODE, $text);
        $this->assertStringContainsString($ttlMinutes.' menit', $text);
        $this->assertStringContainsString('abaikan', $text);
    }

    public function test_job_payload_never_contains_the_otp_code(): void
    {
        $pending = $this->makePending();
        $payload = json_encode(new SendRegistrationOtpJob($pending->id, $this->sentAtOf($pending)));

        $this->assertIsString($payload);
        $this->assertStringNotContainsString(self::CODE, $payload);
    }

    public function test_send_failure_is_logged_without_the_code(): void
    {
        Log::spy();
        Mail::shouldReceive('to->send')->once()->andThrow(new RuntimeException('smtp mati'));
        $pending = $this->makePending();

        try {
            (new SendRegistrationOtpJob($pending->id, $this->sentAtOf($pending)))->handle();
            $this->fail('Kegagalan kirim seharusnya dilempar ulang agar job di-retry.');
        } catch (RuntimeException $exception) {
            $this->assertSame('smtp mati', $exception->getMessage());
        }

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context = []): bool => ! str_contains($message, $pending->id)
                && ! str_contains($message, self::CODE)
                && ! str_contains(json_encode($context) ?: '', self::CODE),
        );
    }

    /**
     * Pending aktif untuk email uji; kolom OTP diisi lewat forceFill karena tidak mass-assignable.
     *
     * @param  array<string, mixed>  $otpOverrides
     */
    private function makePending(array $otpOverrides = []): PendingRegistration
    {
        $pending = PendingRegistration::create([
            'name' => 'Uji',
            'email' => self::EMAIL,
            'password' => 'rahasia123',
        ]);

        $pending->forceFill(array_merge([
            'otp_code' => self::CODE,
            'otp_expires_at' => now()->addMinutes((int) config('otp.ttl_minutes')),
            'otp_last_sent_at' => now(),
        ], $otpOverrides))->save();

        return $pending;
    }

    /** `sentAt` yang dikirim service saat dispatch: ISO8601 dari `otp_last_sent_at`. */
    private function sentAtOf(PendingRegistration $pending): string
    {
        $this->assertNotNull($pending->otp_last_sent_at);

        return $pending->otp_last_sent_at->toIso8601String();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\RegistrationOtpService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Halaman + endpoint OTP: tampil status, verifikasi, kirim ulang, dan batalkan.
 */
class VerifyOtpFlowTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'verif@example.test';

    private const OTHER_EMAIL = 'lain@example.test';

    private const PASSWORD = 'rahasia123';

    private const CANCEL_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Cache::flush();
        $this->seed(RoleSeeder::class);
    }

    public function test_verify_returns_json_redirect_for_auto_submit(): void
    {
        $service = app(\App\Services\Auth\RegistrationOtpService::class);
        $service->start(['name' => 'Auto', 'email' => 'auto-verify@example.test', 'password' => 'rahasia123']);
        $code = $service->currentCode('auto-verify@example.test');

        $this->withSession(['otp.pending_email' => 'auto-verify@example.test'])
            ->postJson(route('auth.otp.verify'), ['code' => $code])
            ->assertOk()
            ->assertJson(['verified' => true, 'redirect' => route('dashboard', absolute: false)]);

        $this->assertAuthenticated();
    }

    public function test_verify_success_logs_user_in_and_deletes_pending(): void
    {
        $service = app(RegistrationOtpService::class);
        $service->start($this->payload());
        $code = (string) $service->currentCode(self::EMAIL);
        $this->withSession($this->pendingSession());

        $this->post(route('auth.otp.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('otp.pending_email')
            ->assertSessionMissing('otp.cancel_token');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => self::EMAIL]);
        $this->assertNotNull(User::query()->where('email', self::EMAIL)->firstOrFail()->email_verified_at);
        $this->assertDatabaseMissing('pending_registrations', ['email' => self::EMAIL]);
    }

    public function test_show_without_pending_session_redirects_to_register(): void
    {
        $this->get(route('auth.otp.show'))->assertRedirect(route('auth.register'));
    }

    public function test_show_renders_otp_status_props(): void
    {
        app(RegistrationOtpService::class)->start($this->payload());
        $this->withSession($this->pendingSession());

        $this->get(route('auth.otp.show'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Halaman Vue milik Task 6; Task 5 hanya menguji kontrak props.
                ->component('Auth/VerifyOtp', false)
                ->where('email', self::EMAIL)
                ->where('sendsRemaining', (int) config('otp.max_sends') - 1)
                ->where('attemptsRemaining', (int) config('otp.max_attempts'))
                ->where('lockedUntil', null)
                ->where('expiresAt', fn (string $value): bool => $value !== '')
                ->where('serverNow', fn (string $value): bool => $value !== '')
                ->where('resendAvailableAt', fn (string $value): bool => $value !== ''));
    }

    public function test_wrong_code_returns_error_and_keeps_guest_out(): void
    {
        $service = app(RegistrationOtpService::class);
        $service->start($this->payload());
        $wrongCode = $this->wrongCode($service->currentCode(self::EMAIL));
        $this->withSession($this->pendingSession());

        $this->from(route('auth.otp.show'))
            ->post(route('auth.otp.verify'), ['code' => $wrongCode])
            ->assertRedirect(route('auth.otp.show'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertDatabaseHas('pending_registrations', ['email' => self::EMAIL, 'otp_attempts' => 1]);
    }

    public function test_expired_code_is_rejected(): void
    {
        $service = app(RegistrationOtpService::class);
        $service->start($this->payload());
        $code = (string) $service->currentCode(self::EMAIL);
        $this->withSession($this->pendingSession());

        $this->travel((int) config('otp.ttl_minutes') + 1)->minutes();

        $this->from(route('auth.otp.show'))
            ->post(route('auth.otp.verify'), ['code' => $code])
            ->assertRedirect(route('auth.otp.show'))
            ->assertSessionHasErrors('code');

        $this->assertStringContainsString('kedaluwarsa', (string) session('errors')->first('code'));
        $this->assertGuest();
    }

    public function test_verify_uses_session_email_instead_of_request_body(): void
    {
        $service = app(RegistrationOtpService::class);
        $service->start($this->payload());
        $code = (string) $service->currentCode(self::EMAIL);
        $this->withSession($this->pendingSession());

        $this->post(route('auth.otp.verify'), [
            'email' => 'penyerang@example.test',
            'code' => $code,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs(User::query()->where('email', self::EMAIL)->firstOrFail());
        $this->assertDatabaseMissing('users', ['email' => 'penyerang@example.test']);
    }

    public function test_resend_ignores_email_from_request_body(): void
    {
        app(RegistrationOtpService::class)->start($this->payload());
        $this->withSession($this->pendingSession());
        $this->travel((int) config('otp.resend_cooldown_seconds') + 1)->seconds();

        $this->post(route('auth.otp.resend'), ['email' => self::OTHER_EMAIL])
            ->assertRedirect(route('auth.otp.show'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pending_registrations', ['email' => self::EMAIL, 'otp_sends' => 2]);
        $this->assertDatabaseMissing('pending_registrations', ['email' => self::OTHER_EMAIL]);
    }

    public function test_verify_without_session_redirects_to_register(): void
    {
        $this->post(route('auth.otp.verify'), ['code' => '123456'])
            ->assertRedirect(route('auth.register'));

        $this->assertGuest();
    }

    public function test_resend_without_session_redirects_to_register(): void
    {
        $this->post(route('auth.otp.resend'))->assertRedirect(route('auth.register'));
    }

    public function test_otp_routes_use_spec_uris(): void
    {
        $this->assertSame('/auth/verify-otp', parse_url(route('auth.otp.show'), PHP_URL_PATH));
        $this->assertSame('/auth/verify-otp', parse_url(route('auth.otp.verify'), PHP_URL_PATH));
        $this->assertSame('/auth/verify-otp/resend', parse_url(route('auth.otp.resend'), PHP_URL_PATH));
        $this->assertSame('/auth/verify-otp/cancel', parse_url(route('auth.otp.cancel'), PHP_URL_PATH));
    }

    public function test_resend_during_cooldown_is_rejected_with_waiting_time(): void
    {
        app(RegistrationOtpService::class)->start($this->payload());
        $this->withSession($this->pendingSession());

        $this->from(route('auth.otp.show'))
            ->post(route('auth.otp.resend'))
            ->assertRedirect(route('auth.otp.show'))
            ->assertSessionHasErrors('resend');

        $message = (string) session('errors')->first('resend');

        $this->assertStringContainsString('Tunggu', $message);
        $this->assertMatchesRegularExpression('/\d+ detik/', $message);
        $this->assertDatabaseHas('pending_registrations', ['email' => self::EMAIL, 'otp_sends' => 1]);
    }

    public function test_resend_after_send_quota_is_locked(): void
    {
        $service = app(RegistrationOtpService::class);

        for ($send = 0; $send < (int) config('otp.max_sends'); $send++) {
            $service->start($this->payload());
        }

        $this->withSession($this->pendingSession());

        $this->from(route('auth.otp.show'))
            ->post(route('auth.otp.resend'))
            ->assertRedirect(route('auth.otp.show'))
            ->assertSessionHasErrors('resend');

        $this->assertStringContainsString('Batas pengiriman', (string) session('errors')->first('resend'));
        $this->assertDatabaseHas('pending_registrations', [
            'email' => self::EMAIL,
            'otp_sends' => (int) config('otp.max_sends'),
        ]);
    }

    public function test_resend_after_cooldown_issues_new_code(): void
    {
        $service = app(RegistrationOtpService::class);
        $service->start($this->payload());
        $firstCode = $service->currentCode(self::EMAIL);
        $this->withSession($this->pendingSession());

        $this->travel((int) config('otp.resend_cooldown_seconds') + 1)->seconds();

        $this->post(route('auth.otp.resend'))
            ->assertRedirect(route('auth.otp.show'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pending_registrations', ['email' => self::EMAIL, 'otp_sends' => 2]);
        $this->assertNotSame($firstCode, $service->currentCode(self::EMAIL));
    }

    public function test_cancel_deletes_pending_and_clears_session(): void
    {
        app(RegistrationOtpService::class)->start($this->payload());
        $this->withSession($this->pendingSession());

        $this->post(route('auth.otp.cancel'))
            ->assertRedirect(route('auth.register'))
            ->assertSessionMissing('otp.pending_email')
            ->assertSessionMissing('otp.cancel_token');

        $this->assertDatabaseMissing('pending_registrations', ['email' => self::EMAIL]);
    }

    public function test_cancel_without_session_token_keeps_pending(): void
    {
        app(RegistrationOtpService::class)->start($this->payload());
        $this->withSession(['otp.pending_email' => self::EMAIL]);

        $this->post(route('auth.otp.cancel'))->assertRedirect(route('auth.register'));

        $this->assertDatabaseHas('pending_registrations', ['email' => self::EMAIL]);
    }

    public function test_verify_is_throttled_per_ip_and_email(): void
    {
        $service = app(RegistrationOtpService::class);
        $service->start($this->payload());
        $wrong = $this->wrongCode($service->currentCode(self::EMAIL));

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withSession($this->pendingSession())
                ->from(route('auth.otp.show'))
                ->post(route('auth.otp.verify'), ['code' => $wrong])
                ->assertRedirect(route('auth.otp.show'));
        }

        $this->withSession($this->pendingSession())
            ->post(route('auth.otp.verify'), ['code' => $wrong])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_verify_throttle_bucket_is_per_email_not_only_ip(): void
    {
        $service = app(RegistrationOtpService::class);
        $service->start($this->payload());
        $service->start(['name' => 'Uji', 'email' => self::OTHER_EMAIL, 'password' => self::PASSWORD]);
        $wrong = $this->wrongCode($service->currentCode(self::EMAIL));

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withSession($this->pendingSession())
                ->post(route('auth.otp.verify'), ['code' => $wrong])
                ->assertRedirect(route('auth.otp.show'));
        }

        $this->withSession($this->pendingSession())
            ->post(route('auth.otp.verify'), ['code' => $wrong])
            ->assertStatus(429);

        // Email lain pada IP yang sama tetap punya bucket sendiri.
        $this->withSession([
            'otp.pending_email' => self::OTHER_EMAIL,
            'otp.cancel_token' => self::CANCEL_TOKEN,
        ])->from(route('auth.otp.show'))
            ->post(route('auth.otp.verify'), ['code' => $this->wrongCode($service->currentCode(self::OTHER_EMAIL))])
            ->assertRedirect(route('auth.otp.show'));
    }

    public function test_resend_is_throttled_per_ip(): void
    {
        app(RegistrationOtpService::class)->start($this->payload());

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withSession($this->pendingSession())
                ->from(route('auth.otp.show'))
                ->post(route('auth.otp.resend'))
                ->assertRedirect(route('auth.otp.show'));
        }

        $this->withSession($this->pendingSession())
            ->post(route('auth.otp.resend'))
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_register_is_capped_per_email_across_ips(): void
    {
        $payload = [
            'name' => 'Uji',
            'email' => 'cap@example.test',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$attempt])
                ->post(route('auth.register.store'), $payload)
                ->assertSessionHasErrors('password');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.6'])
            ->post(route('auth.register.store'), $payload)
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    /**
     * Data pendaftaran valid untuk service.
     *
     * @return array{name: string, email: string, password: string}
     */
    private function payload(): array
    {
        return [
            'name' => 'Uji',
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ];
    }

    /**
     * Sesi seolah berasal dari submit form register.
     *
     * @return array{otp.pending_email: string, otp.cancel_token: string}
     */
    private function pendingSession(): array
    {
        return [
            'otp.pending_email' => self::EMAIL,
            'otp.cancel_token' => self::CANCEL_TOKEN,
        ];
    }

    /** Kode 6 digit yang dijamin tidak sama dengan kode aktif. */
    private function wrongCode(?string $active): string
    {
        return $active === '000000' ? '000001' : '000000';
    }
}

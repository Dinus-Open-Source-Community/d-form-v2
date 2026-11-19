<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendEmailVerificationOtpJob;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Halaman + endpoint verifikasi email akun (spec §6.3, plan Task 3). */
class EmailVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Bucket limiter dan cache kode lokal harus mulai dari nol di tiap test.
        Cache::flush();
        $this->service = app(EmailVerificationService::class);
    }

    public function test_guest_cannot_open_the_verification_page(): void
    {
        $this->get(route('verification.notice'))->assertRedirect(route('auth.login'));
        $this->post(route('verification.send'))->assertRedirect(route('auth.login'));
        $this->post(route('verification.confirm'), ['code' => '123456'])->assertRedirect(route('auth.login'));
    }

    public function test_verified_user_opening_the_notice_is_redirected_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_unverified_user_sees_the_verification_page_with_status_props(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'flow@example.test']);

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Halaman Vue milik Task 7; Task 3 hanya menguji kontrak props.
                ->component('Auth/VerifyEmail', false)
                ->where('email', 'flow@example.test')
                ->where('verified', false)
                ->where('expiresAt', null)
                ->where('attemptsRemaining', 0)
                ->where('sendsRemaining', (int) config('otp.max_sends'))
                ->where('lockedUntil', null)
                ->where('resendAvailableAt', null)
                ->where('serverNow', fn (string $value): bool => $value !== ''));
    }

    public function test_send_issues_a_challenge_and_returns_to_the_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->getKey(),
            'sends' => 1,
            'attempts' => 0,
        ]);
    }

    public function test_send_returns_the_fresh_status_to_json_clients(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'json@example.test']);

        $this->actingAs($user)
            ->postJson(route('verification.send'))
            ->assertOk()
            ->assertJson([
                'email' => 'json@example.test',
                'verified' => false,
                'sendsRemaining' => (int) config('otp.max_sends') - 1,
                'attemptsRemaining' => (int) config('otp.max_attempts'),
            ]);
    }

    public function test_send_dispatches_the_email_job_without_the_code(): void
    {
        Queue::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'));

        $challenge = EmailVerificationCode::query()->where('user_id', $user->getKey())->first();
        $this->assertNotNull($challenge);

        Queue::assertPushed(SendEmailVerificationOtpJob::class, function (SendEmailVerificationOtpJob $job) use ($challenge): bool {
            return $job->userId === $challenge->user_id
                && $job->sentAt === $challenge->last_sent_at?->toIso8601String()
                && ! property_exists($job, 'code');
        });
    }

    public function test_send_for_a_verified_user_redirects_without_a_challenge(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->getKey()]);
    }

    public function test_confirm_with_the_active_code_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'konfirmasi@example.test']);
        $this->actingAs($user)->post(route('verification.send'));

        $this->actingAs($user)
            ->post(route('verification.confirm'), ['code' => $this->activeCode($user)])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()?->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->getKey()]);
    }

    public function test_confirm_returns_the_verified_redirect_to_json_clients(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post(route('verification.send'));

        $this->actingAs($user)
            ->postJson(route('verification.confirm'), ['code' => $this->activeCode($user)])
            ->assertOk()
            ->assertJson(['verified' => true, 'redirect' => route('dashboard', absolute: false)]);
    }

    public function test_confirm_with_a_wrong_code_counts_the_attempt(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post(route('verification.send'));

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.confirm'), ['code' => $this->wrongCode($this->activeCode($user))])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->getKey(),
            'attempts' => 1,
        ]);
        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_third_wrong_confirm_burns_the_code(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post(route('verification.send'));
        $code = $this->activeCode($user);
        $wrong = $this->wrongCode($code);

        for ($attempt = 1; $attempt <= (int) config('otp.max_attempts'); $attempt++) {
            $this->actingAs($user)
                ->post(route('verification.confirm'), ['code' => $wrong])
                ->assertSessionHasErrors('code');
        }

        $this->actingAs($user)
            ->post(route('verification.confirm'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()?->email_verified_at);
        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->getKey(),
            'attempts' => (int) config('otp.max_attempts'),
            'code_hash' => null,
        ]);
    }

    public function test_expired_code_is_rejected_with_an_expiry_message(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post(route('verification.send'));
        $code = $this->activeCode($user);

        $this->travel((int) config('otp.ttl_minutes') + 1)->minutes();

        $this->actingAs($user)
            ->post(route('verification.confirm'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertStringContainsString('kedaluwarsa', (string) session('errors')->first('code'));
        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_confirm_uses_the_session_user_and_ignores_the_body_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'sesi@example.test']);
        $this->actingAs($user)->post(route('verification.send'));

        $this->actingAs($user)
            ->post(route('verification.confirm'), [
                'email' => 'penyerang@example.test',
                'code' => $this->activeCode($user),
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()?->email_verified_at);
        $this->assertDatabaseMissing('users', ['email' => 'penyerang@example.test']);
    }

    public function test_a_code_belonging_to_another_user_is_rejected(): void
    {
        $owner = User::factory()->unverified()->create(['email' => 'pemilik-kode@example.test']);
        $other = User::factory()->unverified()->create(['email' => 'pemakai-lain@example.test']);

        $this->actingAs($owner)->post(route('verification.send'));
        $this->actingAs($other)->post(route('verification.send'));

        $this->actingAs($other)
            ->post(route('verification.confirm'), [
                'email' => $owner->email,
                'code' => $this->activeCode($owner),
            ])
            ->assertSessionHasErrors('code');

        $this->assertNull($other->fresh()?->email_verified_at);
        $this->assertNull($owner->fresh()?->email_verified_at);
        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $other->getKey(),
            'attempts' => 1,
        ]);
    }

    public function test_confirm_rejects_a_malformed_code(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.confirm'), ['code' => 'abc'])
            ->assertSessionHasErrors('code');
    }

    public function test_send_is_throttled_per_user(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        for ($attempt = 1; $attempt <= (int) config('auth_throttle.verify_email_send.max_attempts'); $attempt++) {
            $this->actingAs($user)->post(route('verification.send'))->assertRedirect(route('verification.notice'));
        }

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // Bucket limiter memakai user id, jadi akun lain pada IP yang sama tidak ikut terblokir.
        $this->actingAs($other)->post(route('verification.send'))->assertRedirect(route('verification.notice'));
    }

    public function test_confirm_is_throttled_per_user(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post(route('verification.send'));
        $wrong = $this->wrongCode($this->activeCode($user));

        for ($attempt = 1; $attempt <= (int) config('auth_throttle.verify_email_confirm.max_attempts'); $attempt++) {
            $this->actingAs($user)
                ->post(route('verification.confirm'), ['code' => $wrong])
                ->assertSessionHasErrors('code');
        }

        $this->actingAs($user)
            ->post(route('verification.confirm'), ['code' => $wrong])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_routes_use_the_spec_uris(): void
    {
        $this->assertSame('/email/verify', parse_url(route('verification.notice'), PHP_URL_PATH));
        $this->assertSame('/email/verify/send', parse_url(route('verification.send'), PHP_URL_PATH));
        $this->assertSame('/email/verify/confirm', parse_url(route('verification.confirm'), PHP_URL_PATH));
    }

    /** Kode aktif milik user; job pengirim berjalan sebaris di driver sync. */
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
}

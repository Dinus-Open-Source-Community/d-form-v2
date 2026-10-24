<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bucket limiter memakai cache array; kosongkan agar tiap test mulai dari nol.
        Cache::flush();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $email = 'throttle.login@example.test';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('auth.login.store'), [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $response = $this->post(route('auth.login.store'), [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $response->assertHeader('Retry-After');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));

        // Halaman login (GET) tidak ikut terthrottle karena batas hanya untuk percobaan submit.
        $this->get(route('auth.login'))->assertOk();
    }

    public function test_login_throttle_is_scoped_per_email_and_ip(): void
    {
        $blockedEmail = 'throttle.blocked@example.test';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('auth.login.store'), [
                'email' => $blockedEmail,
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('auth.login.store'), [
            'email' => $blockedEmail,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->post(route('auth.login.store'), [
            'email' => 'throttle.other@example.test',
            'password' => 'wrong-password',
        ])->assertRedirect();
    }

    public function test_login_with_array_email_is_not_a_server_error(): void
    {
        $this->post(route('auth.login.store'), [
            'email' => ['array@example.test'],
            'password' => 'wrong-password',
        ])->assertStatus(302)->assertSessionHasErrors('email');
    }

    public function test_register_is_throttled_after_three_attempts(): void
    {
        $payload = [
            'name' => 'Throttle User',
            'email' => 'throttle.register@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('auth.register.store'), $payload)
                ->assertSessionHasErrors('password');
        }

        $this->post(route('auth.register.store'), $payload)
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_forgot_password_and_reset_link_use_separate_buckets(): void
    {
        $email = 'throttle.forgot@example.test';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('auth.password.email'), ['email' => $email])
                ->assertRedirect();
        }

        $this->post(route('auth.password.email'), ['email' => $email])
            ->assertStatus(429);

        $this->postJson(route('auth.password.reset-link'), ['email' => $email])
            ->assertOk()
            ->assertJson([
                'message' => 'If an account exists for that email, we sent a password reset link.',
            ]);
    }

    public function test_html_throttled_response_keeps_retry_after_header(): void
    {
        $email = 'throttle.html@example.test';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('auth.login.store'), [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $response = $this->post(route('auth.login.store'), [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $response->assertHeader('Retry-After');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));

        $this->assertSame(
            'Error',
            $response->viewData('page')['component'] ?? null,
        );
    }
}

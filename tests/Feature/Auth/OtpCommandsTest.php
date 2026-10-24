<?php

namespace Tests\Feature\Auth;

use App\Models\PendingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_prints_the_active_code(): void
    {
        $this->createPending('cmd@example.test', '654321');

        $this->artisan('otp:current', ['email' => 'cmd@example.test'])
            ->expectsOutput('654321')
            ->assertSuccessful();
    }

    public function test_current_fails_when_no_active_code_exists(): void
    {
        $this->createPending('hangus@example.test', null);

        $this->artisan('otp:current', ['email' => 'hangus@example.test'])
            ->assertExitCode(1);
    }

    public function test_current_is_refused_outside_local_and_testing(): void
    {
        $this->createPending('prod@example.test', '654321');
        $this->app['env'] = 'production';

        $this->artisan('otp:current', ['email' => 'prod@example.test'])
            ->doesntExpectOutput('654321')
            ->assertExitCode(1);
    }

    /** Buat baris pending langsung; kode null meniru OTP yang sudah hangus. */
    private function createPending(string $email, ?string $code): void
    {
        PendingRegistration::create([
            'name' => 'Uji',
            'email' => $email,
            'password' => 'rahasia123',
            'otp_code' => $code,
            'otp_expires_at' => $code === null ? null : now()->addMinutes(10),
        ]);
    }
}

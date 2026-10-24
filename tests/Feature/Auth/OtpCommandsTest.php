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

    public function test_prune_deletes_only_stale_pending(): void
    {
        $stale = $this->createPending('lama@example.test', '111111');
        $stale->forceFill(['updated_at' => now()->subDay()->subMinute()])->save();

        $fresh = $this->createPending('baru@example.test', '222222');

        $this->artisan('otp:prune')->assertSuccessful();

        $this->assertDatabaseMissing('pending_registrations', ['id' => $stale->id]);
        $this->assertDatabaseHas('pending_registrations', ['id' => $fresh->id]);
        $this->assertDatabaseCount('pending_registrations', 1);
    }

    public function test_prune_succeeds_when_nothing_to_delete(): void
    {
        $this->artisan('otp:prune')->assertSuccessful();
    }

    /** Buat baris pending langsung; kode null meniru OTP yang sudah hangus. */
    private function createPending(string $email, ?string $code): PendingRegistration
    {
        return PendingRegistration::forceCreate([
            'name' => 'Uji',
            'email' => $email,
            'password' => 'rahasia123',
            'otp_code' => $code,
            'otp_expires_at' => $code === null ? null : now()->addMinutes(10),
        ]);
    }
}

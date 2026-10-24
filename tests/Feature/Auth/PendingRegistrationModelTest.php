<?php

namespace Tests\Feature\Auth;

use App\Models\PendingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Memastikan OTP tersimpan terenkripsi dan password pending tersimpan sebagai hash.
 */
class PendingRegistrationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_registration_encrypts_otp_and_hashes_password(): void
    {
        $pending = PendingRegistration::forceCreate([
            'name' => 'Uji', 'email' => 'pending@example.test',
            'password' => 'rahasia123', 'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $raw = DB::table('pending_registrations')->where('id', $pending->id)->first();
        $this->assertNotSame('123456', $raw->otp_code);
        $this->assertSame('123456', $pending->fresh()->otp_code);
        $this->assertTrue(Hash::check('rahasia123', $raw->password));
        $this->assertArrayNotHasKey('otp_code', $pending->toArray());
        $this->assertArrayNotHasKey('password', $pending->toArray());
    }

    public function test_config_otp_memakai_nilai_bawaan(): void
    {
        $this->assertSame(6, config('otp.length'));
        $this->assertSame(5, config('otp.ttl_minutes'));
        $this->assertSame(3, config('otp.max_attempts'));
        $this->assertSame(3, config('otp.max_sends'));
        $this->assertSame(15, config('otp.lock_minutes'));
        $this->assertSame(60, config('otp.resend_cooldown_seconds'));
    }
}

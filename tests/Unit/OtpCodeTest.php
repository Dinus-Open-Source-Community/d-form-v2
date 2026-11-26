<?php

namespace Tests\Unit;

use App\Support\Otp\OtpCode;
use Tests\TestCase;

/** Kontrak utilitas kode OTP: angka sepanjang `length`, HMAC-SHA256 dengan APP_KEY, banding aman. */
class OtpCodeTest extends TestCase
{
    public function test_generate_returns_only_digits_with_the_requested_length(): void
    {
        foreach ([4, 6, 8] as $length) {
            $code = OtpCode::generate($length);

            $this->assertSame($length, strlen($code));
            $this->assertMatchesRegularExpression('/^\d+$/', $code);
        }
    }

    public function test_generate_keeps_the_full_length_when_leading_zeroes_appear(): void
    {
        // 200 sampel menutup peluang angka berawalan nol terpotong zero-pad.
        for ($sample = 0; $sample < 200; $sample++) {
            $this->assertSame(6, strlen(OtpCode::generate(6)));
        }
    }

    public function test_hash_is_hmac_sha256_of_the_code_with_the_application_key(): void
    {
        $this->assertSame(
            hash_hmac('sha256', '012345', (string) config('app.key')),
            OtpCode::hash('012345'),
        );

        $this->assertSame(64, strlen(OtpCode::hash('012345')));
    }

    public function test_hash_is_deterministic_and_depends_on_the_code(): void
    {
        $this->assertSame(OtpCode::hash('123456'), OtpCode::hash('123456'));
        $this->assertNotSame(OtpCode::hash('123456'), OtpCode::hash('123457'));
    }

    public function test_matches_accepts_the_same_code(): void
    {
        $this->assertTrue(OtpCode::matches('123456', OtpCode::hash('123456')));
    }

    public function test_matches_rejects_a_different_code(): void
    {
        $this->assertFalse(OtpCode::matches('123457', OtpCode::hash('123456')));
    }

    public function test_matches_rejects_a_tampered_or_empty_hash(): void
    {
        $hash = OtpCode::hash('123456');
        $lastCharacter = substr($hash, -1);
        $tampered = substr($hash, 0, -1).($lastCharacter === '0' ? '1' : '0');

        $this->assertFalse(OtpCode::matches('123456', $tampered));
        $this->assertFalse(OtpCode::matches('123456', ''));
    }
}

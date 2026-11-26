<?php

namespace Tests\Unit\Services\Form;

use App\Services\Form\GuestFormToken;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class GuestFormTokenTest extends TestCase
{
    public function test_issue_returns_a_64_character_random_hex_token(): void
    {
        $token = GuestFormToken::issue();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertNotSame($token, GuestFormToken::issue());
    }

    public function test_hash_is_deterministic_lowercase_sha256(): void
    {
        $this->assertSame(hash('sha256', 'token'), GuestFormToken::hash('token'));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', GuestFormToken::hash('token'));
    }

    public function test_cookie_has_guest_security_attributes_and_configured_lifetime(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-21 12:00:00'));
        config(['guest.cookie_lifetime' => 525600, 'session.secure' => true]);

        $cookie = GuestFormToken::cookie('raw-token');

        $this->assertSame(GuestFormToken::COOKIE_NAME, $cookie->getName());
        $this->assertSame('raw-token', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('2027-11-21 12:00:00', $cookie->getExpiresTime() === false ? null : date('Y-m-d H:i:s', $cookie->getExpiresTime()));

        Carbon::setTestNow();
    }
}

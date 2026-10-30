<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_is_redirected_home_from_auth_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.login'))
            ->assertRedirect('/');

        $this->actingAs($user)
            ->get(route('auth.register'))
            ->assertRedirect('/');

        $this->actingAs($user)
            ->get(route('auth.password.request'))
            ->assertRedirect('/');

        $this->actingAs($user)
            ->get(route('password.reset', ['token' => 'dummy-token']))
            ->assertRedirect('/');

        $response = $this->actingAs($user)->get('/auth');
        $response->assertRedirect('/');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_guest_can_still_open_auth_pages(): void
    {
        $this->get(route('auth.login'))->assertOk();
        $this->get(route('auth.register'))->assertOk();
        $this->get(route('auth.password.request'))->assertOk();
    }

    public function test_auth_pages_send_no_store_cache_headers(): void
    {
        foreach ([route('auth.login'), route('auth.register'), route('auth.password.request')] as $url) {
            $response = $this->get($url);

            $response->assertOk();

            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $this->assertSame('no-cache', $response->headers->get('Pragma'));
        }
    }

    public function test_authenticated_redirect_response_also_has_no_store(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('auth.login'));

        $response->assertRedirect('/');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}

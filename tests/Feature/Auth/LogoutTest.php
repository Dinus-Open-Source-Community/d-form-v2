<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_is_logged_out_and_redirected_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('auth.logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }
}

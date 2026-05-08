<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_forgot_password_page(): void
    {
        $this->get(route('auth.password.request'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/ForgotPassword'));
    }

    public function test_guest_can_view_reset_password_page_with_token(): void
    {
        $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'u@example.com']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', 'test-token')
                ->where('email', 'u@example.com'));
    }

    public function test_forgot_password_sends_reset_notification_for_valid_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->from(route('auth.password.request'))
            ->post(route('auth.password.email'), [
                'email' => $user->email,
            ])
            ->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_updates_credentials_and_redirects_to_login(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('old-secret'),
        ]);

        $this->from(route('auth.password.request'))
            ->post(route('auth.password.email'), [
                'email' => $user->email,
            ]);

        $sent = Notification::sent($user, ResetPassword::class);
        $this->assertCount(1, $sent);
        $notification = $sent->first();
        $this->assertInstanceOf(ResetPassword::class, $notification);

        $this->post(route('auth.password.update', ['token' => $notification->token]), [
            'email' => $user->email,
            'password' => 'new-secret-99',
            'password_confirmation' => 'new-secret-99',
        ])
            ->assertRedirect(route('auth.login'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-99', $user->password));
    }
}

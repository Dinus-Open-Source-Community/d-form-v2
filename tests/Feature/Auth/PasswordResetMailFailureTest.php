<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\TestCase;

class PasswordResetMailFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_synchronous_delivery_failure_keeps_forgot_password_response_anti_enumerating(): void
    {
        $user = User::factory()->create([
            'email' => 'known.account@example.test',
        ]);
        $unknownEmail = 'unknown.account@example.test';
        $transportError = 'SMTP transport at smtp.private.test rejected known.account@example.test password=transport-secret';

        Log::spy();
        Password::shouldReceive('sendResetLink')
            ->twice()
            ->andReturnUsing(static function (array $credentials) use ($user, $transportError): string {
                if ($credentials['email'] === $user->email) {
                    throw new RuntimeException($transportError);
                }

                return 'passwords.user';
            });

        $knownResponse = $this->from(route('auth.password.request'))
            ->post(route('auth.password.email'), ['email' => $user->email]);
        $unknownResponse = $this->from(route('auth.password.request'))
            ->post(route('auth.password.email'), ['email' => $unknownEmail]);

        $expectedToast = [
            'message' => 'If an account exists for that email, we sent a password reset link.',
            'type' => 'success',
        ];

        $knownResponse->assertRedirect()
            ->assertSessionHas('inertia.flash_data', fn (array $flash): bool => ($flash['toast'] ?? null) === $expectedToast);
        $unknownResponse->assertRedirect()
            ->assertSessionHas('inertia.flash_data', fn (array $flash): bool => ($flash['toast'] ?? null) === $expectedToast);

        $knownSession = json_encode($knownResponse->getSession()->all(), JSON_THROW_ON_ERROR);
        $unknownSession = json_encode($unknownResponse->getSession()->all(), JSON_THROW_ON_ERROR);
        foreach ([$knownSession, $unknownSession, $knownResponse->getContent(), $unknownResponse->getContent()] as $responseText) {
            self::assertStringNotContainsString($user->email, $responseText);
            self::assertStringNotContainsString($unknownEmail, $responseText);
            self::assertStringNotContainsString('transport-secret', $responseText);
            self::assertStringNotContainsString('smtp.private.test', $responseText);
        }

        Log::shouldHaveReceived('error')->once()->withArgs(
            static function (string $message, array $context = []) use ($user): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return ! array_key_exists('exception', $context)
                    && ! array_key_exists('previous', $context)
                    && ! str_contains($message.$encoded, $user->email)
                    && ! str_contains($message.$encoded, 'transport-secret');
            },
        );
    }
}

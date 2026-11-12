<?php

namespace Tests\Feature\Broadcasting;

use App\Enums\EmailBroadcastRecipientStatus;
use App\Enums\EmailBroadcastStatus;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastRecipientController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastTestController;
use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class BroadcastErrorProjectionTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_RECIPIENT_EMAIL = 'operational.recipient@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        config(['features.broadcast' => true]);

        Route::middleware('web')->get(
            '/__test/broadcasts/{broadcast}/recipients',
            [BroadcastRecipientController::class, 'index'],
        );
        Route::middleware('web')->post(
            '/__test/broadcasts/{broadcast}/test',
            BroadcastTestController::class,
        );
        Route::middleware(['web', 'auth', 'throttle:broadcast-test'])
            ->get('/__test/broadcasts/rate-limit', static fn () => response()->noContent());
    }

    public function test_recipient_projection_keeps_operational_email_and_hides_transport_details(): void
    {
        $broadcast = $this->broadcast();
        $transportError = 'SMTP transport at smtp.private.test rejected private.recipient@example.test password=transport-secret';

        EmailBroadcastRecipient::query()->create([
            'broadcast_id' => $broadcast->id,
            'name' => 'Operational Recipient',
            'email' => self::TEST_RECIPIENT_EMAIL,
            'status' => EmailBroadcastRecipientStatus::Failed,
            'error_message' => $transportError,
        ]);

        $this->actingAs($this->authorizedUser())
            ->get('/__test/broadcasts/'.$broadcast->id.'/recipients')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recipients.data.0.email', self::TEST_RECIPIENT_EMAIL)
                ->where('recipients.data.0.error_message', 'Pengiriman email gagal.')
                ->where('recipients.data.0.error_category', 'mail_delivery_failed'))
            ->assertDontSee($transportError)
            ->assertDontSee('private.recipient@example.test')
            ->assertDontSee('transport-secret');
    }

    public function test_synchronous_test_mail_failure_returns_generic_toast_and_reports_redacted_context(): void
    {
        $broadcast = $this->broadcast();
        $recipientEmail = 'test.destination@example.test';
        $transportError = 'SMTP transport at smtp.private.test rejected test.destination@example.test password=transport-secret';

        Log::spy();
        Mail::shouldReceive('to->send')
            ->once()
            ->andThrow(new RuntimeException($transportError));

        $response = $this->actingAs($this->authorizedUser())
            ->from('/__test/broadcasts/'.$broadcast->id.'/test')
            ->post('/__test/broadcasts/'.$broadcast->id.'/test', [
                'email' => $recipientEmail,
            ]);

        $response->assertRedirect()
            ->assertSessionHas('toast.message', 'Pengiriman email gagal.')
            ->assertSessionHas('toast.type', 'error')
            ->assertSessionHas('toast.error_category', 'mail_delivery_failed');

        $session = json_encode($response->getSession()->all(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($recipientEmail, $session);
        self::assertStringNotContainsString('transport-secret', $session);
        self::assertStringNotContainsString('smtp.private.test', $session);

        Log::shouldHaveReceived('error')->once()->withArgs(
            static function (string $message, array $context = []) use ($recipientEmail): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return ! array_key_exists('exception', $context)
                    && ! array_key_exists('previous', $context)
                    && ! str_contains($message.$encoded, $recipientEmail)
                    && ! str_contains($message.$encoded, 'transport-secret');
            },
        );
    }

    public function test_broadcast_test_limiter_uses_configured_limit_and_isolates_users(): void
    {
        config(['security.broadcast_test_rate_limit_per_minute' => 2]);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)->get('/__test/broadcasts/rate-limit')->assertNoContent();
        $this->actingAs($firstUser)->get('/__test/broadcasts/rate-limit')->assertNoContent();
        $this->actingAs($firstUser)->get('/__test/broadcasts/rate-limit')->assertStatus(429);
        $this->actingAs($secondUser)->get('/__test/broadcasts/rate-limit')->assertNoContent();
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['email-broadcast.view', 'email-broadcast.create']);

        return $user;
    }

    private function broadcast(): EmailBroadcast
    {
        return EmailBroadcast::query()->create([
            'name' => 'Projection test',
            'scheduled_at' => now()->addDay(),
            'delay_min' => 0,
            'delay_max' => 0,
            'subject' => 'Test subject',
            'content' => '<p>Test content</p>',
            'status' => EmailBroadcastStatus::Draft,
        ]);
    }
}

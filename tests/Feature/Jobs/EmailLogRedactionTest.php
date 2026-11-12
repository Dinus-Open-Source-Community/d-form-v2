<?php

namespace Tests\Feature\Jobs;

use App\Enums\EmailBroadcastRecipientStatus;
use App\Enums\EmailBroadcastStatus;
use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Exceptions\RedactedMailDeliveryException;
use App\Jobs\Broadcasting\SendBroadcastRecipientJob;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Jobs\SendRegistrationOtpJob;
use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Broadcasting\BroadcastDispatchService;
use App\Services\Broadcasting\BroadcastPersonalization;
use App\Support\LogRedaction;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Regression coverage for AUDIT-P2-006: failed email deliveries must not leak raw
 * recipient addresses, credentials, or transport messages into logs or public errors.
 */
class EmailLogRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_send_failure_redacts_pii_from_error_message_and_log(): void
    {
        config(['features.broadcast' => true]);

        $broadcast = EmailBroadcast::query()->create([
            'name' => 'Redaction test',
            'scheduled_at' => now(),
            'delay_min' => 0,
            'delay_max' => 0,
            'subject' => 'Halo {{name}}',
            'content' => '<p>Halo {{name}}</p>',
            'status' => EmailBroadcastStatus::Processing,
        ]);

        $recipient = EmailBroadcastRecipient::query()->create([
            'broadcast_id' => $broadcast->id,
            'name' => 'Penerima',
            'email' => 'e2e.broadcast@example.test',
            'status' => EmailBroadcastRecipientStatus::Pending,
        ]);

        $transportMessage = 'SMTP rejected e2e.broadcast@example.test; applicant=Synthetic Person; nim=EP02-NIM-482915; phone=+62 812-3456-7890 password=supersecret';
        $original = new RuntimeException($transportMessage);

        Log::spy();
        Mail::shouldReceive('to->send')->once()->andThrow($original);

        $job = new SendBroadcastRecipientJob((string) $recipient->id);

        try {
            $job->handle(app(BroadcastPersonalization::class), app(BroadcastDispatchService::class));
            self::fail('Transport failure must be rethrown so the queue can retry.');
        } catch (RedactedMailDeliveryException $exception) {
            $serialized = (string) $exception;

            self::assertSame($original, $exception->getPrevious());
            self::assertStringContainsString('supersecret', (string) $exception->getPrevious()?->getMessage());
            self::assertStringNotContainsString('supersecret', $exception->getMessage());
            self::assertStringNotContainsString('e2e.broadcast@example.test', $exception->getMessage());
            self::assertStringNotContainsString('Synthetic Person', $exception->getMessage());
            self::assertStringNotContainsString('EP02-NIM-482915', $exception->getMessage());
            self::assertStringNotContainsString('+62 812-3456-7890', $exception->getMessage());
            self::assertStringNotContainsString($transportMessage, $serialized);
            self::assertStringNotContainsString('e2e.broadcast@example.test', $serialized);
            self::assertStringNotContainsString('Next ', $serialized);
        }

        $errorMessage = (string) $recipient->refresh()->error_message;

        self::assertStringContainsString(RuntimeException::class, $errorMessage);
        self::assertStringNotContainsString('e2e.broadcast@example.test', $errorMessage);
        self::assertStringNotContainsString('supersecret', $errorMessage);
        self::assertStringNotContainsString('Synthetic Person', $errorMessage);
        self::assertStringNotContainsString('EP02-NIM-482915', $errorMessage);
        self::assertStringNotContainsString('+62 812-3456-7890', $errorMessage);

        Log::shouldHaveReceived('error')->once()->withArgs(
            function (string $message, array $context = []) use ($transportMessage): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return ($context['exception_class'] ?? null) === RuntimeException::class
                    && array_key_exists('error', $context)
                    && $context['error'] === LogRedaction::exception(new RuntimeException($transportMessage))
                    && ($context['recipient'] ?? null) === 'e***@example.test'
                    && ! str_contains($encoded, 'e2e.broadcast@example.test')
                    && ! str_contains($encoded, 'supersecret')
                    && ! str_contains($encoded, 'Synthetic Person')
                    && ! str_contains($encoded, 'EP02-NIM-482915')
                    && ! str_contains($encoded, '+62 812-3456-7890');
            }
        );
    }

    public function test_otp_send_failure_logs_redacted_context(): void
    {
        $pending = PendingRegistration::create([
            'name' => 'Uji',
            'email' => 'otp.person@example.test',
            'password' => 'rahasia123',
        ]);

        $pending->forceFill([
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
            'otp_last_sent_at' => now(),
        ])->save();

        $transportMessage = 'SMTP rejected otp.person@example.test; applicant=Synthetic Person; nim=EP02-NIM-482915; phone=+62 812-3456-7890 token=abc123';
        $original = new RuntimeException($transportMessage);

        Log::spy();
        Mail::shouldReceive('to->send')->once()->andThrow($original);

        $sentAt = $pending->otp_last_sent_at->toIso8601String();
        $job = new SendRegistrationOtpJob((string) $pending->id, $sentAt);

        try {
            $job->handle();
            self::fail('Transport failure must be rethrown so the queue can retry.');
        } catch (RedactedMailDeliveryException $exception) {
            $serialized = (string) $exception;

            self::assertSame($original, $exception->getPrevious());
            self::assertStringContainsString('token=abc123', (string) $exception->getPrevious()?->getMessage());
            self::assertStringNotContainsString('abc123', $exception->getMessage());
            self::assertStringNotContainsString($transportMessage, $serialized);
            self::assertStringNotContainsString('otp.person@example.test', $serialized);
            self::assertStringNotContainsString('Next ', $serialized);
        }

        Log::shouldHaveReceived('error')->once()->withArgs(
            function (string $message, array $context = []) use ($transportMessage): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return ($context['exception_class'] ?? null) === RuntimeException::class
                    && ($context['attempt'] ?? null) === 1
                    && array_key_exists('error', $context)
                    && $context['error'] === LogRedaction::exception(new RuntimeException($transportMessage))
                    && ! str_contains($encoded, 'otp.person@example.test')
                    && ! str_contains($encoded, 'abc123')
                    && ! str_contains($encoded, 'token=abc123')
                    && ! str_contains($encoded, 'Synthetic Person')
                    && ! str_contains($encoded, 'EP02-NIM-482915')
                    && ! str_contains($encoded, '+62 812-3456-7890');
            }
        );
    }

    public function test_permanent_failure_logs_masked_recipients(): void
    {
        $probe = new EmailLogRedactionProbe();
        $probe->deliveryKey = 'probe-delivery-key';

        EmailLog::query()->create([
            'event_id' => Event::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'recipient_email' => 'recipient@example.test',
            'status' => EmailLogStatus::Queued,
            'notification_type' => EmailNotificationType::RegistrationSubmitted,
            'error_message' => null,
            'sent_at' => null,
            'delivery_key' => 'probe-delivery-key',
        ]);

        Log::spy();

        $probe->failed(new RuntimeException('boom user@example.test'));

        Log::shouldHaveReceived('error')->once()->withArgs(
            function (string $message, array $context = []): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);
                $recipients = $context['recipients'] ?? [];

                return ($recipients[0] ?? null) === 'r***@example.test'
                    && ! str_contains($encoded, 'recipient@example.test')
                    && ! str_contains($encoded, 'user@example.test');
            }
        );
    }
}

/**
 * Minimal queue job using the delivery trait so the terminal failure log can be inspected.
 */
final class EmailLogRedactionProbe
{
    use Queueable;
    use TracksOutgoingEmail;
}

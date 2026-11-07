<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\RedactedMailDeliveryException;
use App\Support\LogRedaction;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class RedactedMailDeliveryExceptionTest extends TestCase
{
    private const RAW_MESSAGE = 'SMTP rejected applicant@example.com password=hunter2';

    public function test_from_throwable_redacts_the_message_and_keeps_the_original_chained(): void
    {
        $original = new RuntimeException(self::RAW_MESSAGE);

        $wrapped = RedactedMailDeliveryException::fromThrowable($original);

        self::assertSame(RedactedMailDeliveryException::class, $wrapped::class);
        self::assertSame(LogRedaction::exception($original, 1000), $wrapped->getMessage());
        self::assertStringContainsString('[email]', $wrapped->getMessage());
        self::assertStringContainsString('password=[redacted]', $wrapped->getMessage());
        self::assertSame($original, $wrapped->getPrevious());
    }

    public function test_serialization_never_exposes_the_chained_original(): void
    {
        $wrapped = RedactedMailDeliveryException::fromThrowable(new RuntimeException(self::RAW_MESSAGE));

        $text = (string) $wrapped;

        self::assertStringContainsString(RedactedMailDeliveryException::class, $text);
        self::assertStringNotContainsString(self::RAW_MESSAGE, $text);
        self::assertStringNotContainsString('SMTP rejected', $text);
        self::assertStringNotContainsString('applicant@example.com', $text);
        self::assertStringNotContainsString('hunter2', $text);
        self::assertStringNotContainsString('Next ', $text);
    }

    public function test_serialization_never_renders_frame_arguments(): void
    {
        $wrapped = RedactedMailDeliveryException::fromThrowable(new RuntimeException(self::RAW_MESSAGE));

        $text = (string) $wrapped;

        self::assertStringContainsString('Stack trace:', $text);
        self::assertStringContainsString('#0 ', $text);
        self::assertStringNotContainsString('Object(', $text);
        self::assertStringNotContainsString('hunter2', $text);
        self::assertStringNotContainsString('applicant@example.com', $text);
    }

    public function test_report_logs_no_pii_and_skips_the_default_handler(): void
    {
        $wrapped = RedactedMailDeliveryException::fromThrowable(new RuntimeException(self::RAW_MESSAGE));

        Log::spy();

        self::assertTrue($wrapped->report());

        Log::shouldHaveReceived('error')->once()->withArgs(
            function (string $message, array $context = []): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return ($context['exception_class'] ?? null) === RedactedMailDeliveryException::class
                    && ($context['original_class'] ?? null) === RuntimeException::class
                    && ! str_contains($encoded, 'applicant@example.com')
                    && ! str_contains($encoded, 'hunter2')
                    && ! str_contains($encoded, self::RAW_MESSAGE);
            }
        );
    }

    public function test_handler_reporting_never_serializes_the_chained_original(): void
    {
        $raw = new RuntimeException(self::RAW_MESSAGE);
        $wrapped = RedactedMailDeliveryException::fromThrowable($raw);

        Log::spy();

        app(ExceptionHandler::class)->report($wrapped);

        Log::shouldHaveReceived('error')->once()->withArgs(
            function (string $message, array $context = []): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return ! array_key_exists('exception', $context)
                    && ($context['original_class'] ?? null) === RuntimeException::class
                    && ! str_contains($encoded, 'applicant@example.com')
                    && ! str_contains($encoded, 'hunter2')
                    && ! str_contains($encoded, 'Next ')
                    && ! str_contains($encoded, self::RAW_MESSAGE);
            }
        );
    }
}

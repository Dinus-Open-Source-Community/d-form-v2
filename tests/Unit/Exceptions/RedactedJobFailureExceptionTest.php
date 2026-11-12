<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\RedactedJobFailureException;
use RuntimeException;
use Tests\TestCase;

class RedactedJobFailureExceptionTest extends TestCase
{
    public function test_serialization_is_the_original_class_and_a_redacted_message_only(): void
    {
        $thrown = $this->failWithSensitiveArguments('applicant@example.test', 'token-canary', 'password-canary');

        $serialized = (string) new RedactedJobFailureException($thrown);

        self::assertSame(RuntimeException::class.': [message redacted]', $serialized);
        self::assertStringNotContainsString('Stack trace', $serialized);
        self::assertStringNotContainsString('applicant@example.test', $serialized);
        self::assertStringNotContainsString('token-canary', $serialized);
        self::assertStringNotContainsString('password-canary', $serialized);
    }

    public function test_double_wrapping_reports_the_innermost_original(): void
    {
        $thrown = $this->failWithSensitiveArguments('applicant@example.test', 'token-canary', 'password-canary');
        $inner = new RedactedJobFailureException($thrown);

        $outer = new RedactedJobFailureException($inner);

        self::assertSame(RuntimeException::class.': [message redacted]', (string) $outer);
        self::assertNotSame(RedactedJobFailureException::class, $outer->getPrevious()::class);
        self::assertSame($thrown, $outer->getPrevious());
        self::assertStringNotContainsString('token-canary', $outer->getMessage());
    }

    /** Thrown from a frame with sensitive arguments; a serialization that walked them would leak. */
    private function failWithSensitiveArguments(string $email, string $token, string $password): RuntimeException
    {
        return new RuntimeException("delivery failed for {$email} token={$token} password={$password}");
    }
}

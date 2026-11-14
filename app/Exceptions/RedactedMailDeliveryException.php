<?php

namespace App\Exceptions;

use App\Support\LogRedaction;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/** Wraps a mail transport failure whose original message may contain PII or credentials. */
final class RedactedMailDeliveryException extends RuntimeException
{
    /** Public phrase used for serialization; carries no transport free text. */
    private const PUBLIC_MESSAGE = 'Mail delivery failed.';

    public function __construct(string $publicMessage, ?Throwable $previous = null)
    {
        parent::__construct($publicMessage, 0, $previous);
    }

    /** Build the wrapper from a transport failure using the shared redaction rules. */
    public static function fromThrowable(Throwable $exception): self
    {
        return new self(LogRedaction::exception($exception, 1000), $exception);
    }

    /** Serialize with only the class and a fixed public phrase. */
    public function __toString(): string
    {
        $trace = $this->getTrace();
        $frames = array_map(
            static fn (array $frame, int $index): string => sprintf(
                '#%d %s%s%s() at %s:%s',
                $index,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'],
                $frame['file'] ?? '[internal]',
                $frame['line'] ?? '0',
            ),
            $trace,
            array_keys($trace),
        );

        return sprintf(
            "%s: %s\nStack trace:\n%s",
            static::class,
            self::PUBLIC_MESSAGE,
            implode("\n", $frames),
        );
    }

    /** Report a redacted line for operator triage and skip Laravel's default handler. */
    public function report(): bool
    {
        $previous = $this->getPrevious();

        Log::error('[RedactedMailDeliveryException] Mail delivery failed.', [
            'exception_class' => static::class,
            'original_class' => $previous === null ? null : $previous::class,
            'error' => $previous === null ? null : LogRedaction::exception($previous, 1000),
        ]);

        return true;
    }
}

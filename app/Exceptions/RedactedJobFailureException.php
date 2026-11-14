<?php

namespace App\Exceptions;

use App\Support\LogRedaction;
use RuntimeException;
use Throwable;

/** Throwable handed to failed-job storage drivers that stringify the exception themselves. */
final class RedactedJobFailureException extends RuntimeException
{
    private readonly Throwable $original;

    public function __construct(Throwable $original)
    {
        // A double wrap would report this wrapper as the failing class and lose the original.
        $this->original = $original instanceof self ? $original->original : $original;

        parent::__construct(LogRedaction::exception($this->original), 0, $this->original);
    }

    /** Serialize as `OriginalClass: [message redacted]`; never walk the chained original. */
    public function __toString(): string
    {
        return LogRedaction::exception($this->original);
    }
}

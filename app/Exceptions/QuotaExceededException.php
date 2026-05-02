<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside a DB transaction when the quota re-check (post-lock) fails.
 * Caught by the global exception handler and converted to an Inertia redirect.
 */
class QuotaExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Registration is full. No more submissions are being accepted.');
    }
}

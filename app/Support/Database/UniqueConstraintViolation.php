<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

final class UniqueConstraintViolation
{
    public static function isViolation(QueryException $exception): bool
    {
        return $exception instanceof UniqueConstraintViolationException;
    }
}

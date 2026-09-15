<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;

final class UniqueConstraintViolation
{
    public static function isViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? '') === '23000';
    }
}

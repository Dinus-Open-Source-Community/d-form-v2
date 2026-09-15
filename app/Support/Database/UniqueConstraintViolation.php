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

    /**
     * @param  array<int, string>  $markers
     */
    public static function matches(QueryException $exception, array $markers): bool
    {
        $message = $exception->getMessage();

        foreach ($markers as $marker) {
            if ($marker !== '' && str_contains($message, $marker)) {
                return true;
            }
        }

        return false;
    }
}

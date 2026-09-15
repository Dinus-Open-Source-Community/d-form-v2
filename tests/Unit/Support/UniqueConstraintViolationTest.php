<?php

namespace Tests\Unit\Support;

use App\Support\Database\UniqueConstraintViolation;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class UniqueConstraintViolationTest extends TestCase
{
    public function test_detects_sql_state_23000(): void
    {
        $previous = new PDOException('Duplicate entry');
        $previous->errorInfo = ['23000', 1062, 'Duplicate entry'];

        $exception = new QueryException('mysql', 'insert into t values (1)', [], $previous);

        $this->assertTrue(UniqueConstraintViolation::isViolation($exception));
    }

    public function test_rejects_other_sql_states(): void
    {
        $previous = new PDOException('Table not found');
        $previous->errorInfo = ['42S02', 1146, 'Table not found'];

        $exception = new QueryException('mysql', 'select 1', [], $previous);

        $this->assertFalse(UniqueConstraintViolation::isViolation($exception));
    }

    public function test_rejects_missing_error_info(): void
    {
        $exception = new QueryException('mysql', 'select 1', [], new PDOException('boom'));

        $this->assertFalse(UniqueConstraintViolation::isViolation($exception));
    }
}

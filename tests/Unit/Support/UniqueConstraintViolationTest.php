<?php

namespace Tests\Unit\Support;

use App\Support\Database\UniqueConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use PDOException;
use PHPUnit\Framework\TestCase;

class UniqueConstraintViolationTest extends TestCase
{
    public function test_detects_unique_constraint_violation(): void
    {
        $this->assertTrue(UniqueConstraintViolation::isViolation($this->uniqueViolation()));
    }

    public function test_rejects_foreign_key_violation_with_sql_state_23000(): void
    {
        $previous = new PDOException('Cannot add or update a child row');
        $previous->errorInfo = ['23000', 1452, 'Cannot add or update a child row'];

        $exception = new QueryException('mysql', 'insert into t values (1)', [], $previous);

        $this->assertFalse(UniqueConstraintViolation::isViolation($exception));
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

    public function test_matches_detects_mysql_index_name_marker(): void
    {
        $exception = $this->queryException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'A11' for key 'recruitment_applications.rec_apps_period_nim_uniq'"
        );

        $this->assertTrue(UniqueConstraintViolation::matches(
            $exception,
            ['rec_apps_period_nim_uniq', 'recruitment_applications.nim'],
        ));
    }

    public function test_matches_detects_sqlite_column_marker(): void
    {
        $exception = $this->queryException(
            'UNIQUE constraint failed: recruitment_applications.recruitment_period_id, recruitment_applications.nim'
        );

        $this->assertTrue(UniqueConstraintViolation::matches(
            $exception,
            ['rec_apps_period_nim_uniq', 'recruitment_applications.nim'],
        ));
    }

    public function test_matches_rejects_registration_number_conflict(): void
    {
        $exception = $this->queryException(
            'UNIQUE constraint failed: recruitment_applications.registration_number'
        );

        $this->assertFalse(UniqueConstraintViolation::matches(
            $exception,
            ['rec_apps_period_nim_uniq', 'recruitment_applications.nim'],
        ));
    }

    private function queryException(string $message): QueryException
    {
        return new QueryException('mysql', 'insert into t values (1)', [], new PDOException($message));
    }

    private function uniqueViolation(): UniqueConstraintViolationException
    {
        $previous = new PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry');
        $previous->errorInfo = ['23000', 1062, 'Duplicate entry'];

        return new UniqueConstraintViolationException('mysql', 'insert into t values (1)', [], $previous);
    }
}

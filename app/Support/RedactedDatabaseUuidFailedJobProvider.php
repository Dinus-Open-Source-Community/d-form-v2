<?php

namespace App\Support;

use App\Exceptions\RedactedJobFailureException;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Throwable;

/**
 * Redact the exception column of Laravel's UUID failed-job provider.
 *
 * The parent insert is reused unchanged: {@see RedactedJobFailureException} stringifies to
 * `OriginalClass: [message redacted]`, so `failed_jobs` keeps Laravel's own column set and
 * cannot drift when the framework adds a column.
 */
final class RedactedDatabaseUuidFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    /**
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @param  Throwable  $exception
     * @return string|null
     */
    public function log($connection, $queue, $payload, $exception)
    {
        return parent::log($connection, $queue, $payload, new RedactedJobFailureException($exception));
    }
}

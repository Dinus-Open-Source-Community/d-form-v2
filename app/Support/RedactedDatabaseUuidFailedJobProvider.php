<?php

namespace App\Support;

use App\Exceptions\RedactedJobFailureException;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Throwable;

/** Redact the exception column of Laravel's UUID failed-job provider. */
final class RedactedDatabaseUuidFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    /**
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @param  Throwable  $exception
     * @return string|null
     *
     */
    public function log($connection, $queue, $payload, $exception)
    {
        return parent::log($connection, $queue, $payload, new RedactedJobFailureException($exception));
    }
}

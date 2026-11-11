<?php

namespace App\Support;

use App\Exceptions\RedactedJobFailureException;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Throwable;

/**
 * Decorate a failed-job provider that has no redaction of its own.
 *
 * Laravel's null, file, DynamoDB and legacy database providers store the exception they are
 * given, so the redacted wrapper is substituted before delegation. Providers that support
 * pruning are wrapped by {@see RedactedPrunableFailedJobProvider} instead.
 */
class RedactedFailedJobProvider implements FailedJobProviderInterface
{
    public function __construct(private readonly FailedJobProviderInterface $provider)
    {
    }

    /** Redact exception content before persisting it through the configured queue driver. */
    public function log(mixed $connection, mixed $queue, mixed $payload, mixed $exception): string|int|null
    {
        return $this->provider->log(
            $connection,
            $queue,
            $payload,
            $exception instanceof Throwable ? new RedactedJobFailureException($exception) : $exception,
        );
    }

    public function ids(mixed $queue = null): array
    {
        return $this->provider->ids($queue);
    }

    public function all(): array
    {
        return $this->provider->all();
    }

    public function find(mixed $id): ?object
    {
        return $this->provider->find($id);
    }

    public function forget(mixed $id): bool
    {
        return $this->provider->forget($id);
    }

    public function flush(mixed $hours = null): void
    {
        $this->provider->flush($hours);
    }
}

<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\Failed\PrunableFailedJobProvider;

/** Preserve failed-job pruning when redaction wraps a provider that supports it. */
final class RedactedPrunableFailedJobProvider extends RedactedFailedJobProvider implements PrunableFailedJobProvider
{
    private readonly FailedJobProviderInterface&PrunableFailedJobProvider $prunableProvider;

    public function __construct(FailedJobProviderInterface&PrunableFailedJobProvider $provider)
    {
        parent::__construct($provider);
        $this->prunableProvider = $provider;
    }

    /** Remove failed-job entries older than the supplied cutoff. */
    public function prune(DateTimeInterface $before): int
    {
        return $this->prunableProvider->prune($before);
    }
}

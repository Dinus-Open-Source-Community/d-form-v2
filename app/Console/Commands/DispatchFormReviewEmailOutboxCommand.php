<?php

namespace App\Console\Commands;

use App\Services\Form\FormReviewEmailOutboxRecovery;
use Illuminate\Console\Command;

class DispatchFormReviewEmailOutboxCommand extends Command
{
    protected $signature = 'forms:dispatch-review-email-outbox {--limit=100 : Maximum pending intents to dispatch}';

    protected $description = 'Recover and dispatch pending or stale form-review email intents';

    /** Dispatch one bounded batch so a backlog cannot flood the queue. */
    public function handle(FormReviewEmailOutboxRecovery $recovery): int
    {
        $dispatched = $recovery->dispatchPending((int) $this->option('limit'));
        $this->info("Dispatched {$dispatched} form-review email intent(s).");

        return self::SUCCESS;
    }
}

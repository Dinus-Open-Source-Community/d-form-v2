<?php

namespace App\Console\Commands\Broadcasting;

use App\Services\Broadcasting\BroadcastDispatchService;
use Illuminate\Console\Command;

class DispatchScheduledBroadcastsCommand extends Command
{
    protected $signature = 'broadcast:dispatch-scheduled';

    protected $description = 'Dispatch email broadcasts yang scheduled_at-nya telah tiba ke queue per-recipient.';

    public function handle(BroadcastDispatchService $dispatchService): int
    {
        $processed = $dispatchService->dispatchDue();

        $this->info('Dispatched '.count($processed).' broadcast(s).');

        return self::SUCCESS;
    }
}

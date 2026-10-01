<?php

namespace App\Console\Commands;

use App\Models\Broadcast;
use App\Services\Broadcast\BroadcastDispatchService;
use Illuminate\Console\Command;

class SendScheduledBroadcastsCommand extends Command
{
    protected $signature = 'broadcast:send-scheduled';

    protected $description = 'Dispatch scheduled broadcasts whose scheduled_at is due';

    public function handle(BroadcastDispatchService $dispatchService): int
    {
        $due = Broadcast::query()
            ->where('status', Broadcast::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($due as $broadcast) {
            $dispatchService->sendNow($broadcast);
            $count++;
        }

        $this->info("Dispatched {$count} scheduled broadcast(s).");

        return self::SUCCESS;
    }
}

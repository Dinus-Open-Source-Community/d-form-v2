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
        // Fitur broadcast dinonaktifkan (config/features.php). Sengaja no-op.
        if (! config('features.broadcast', false)) {
            $this->warn('Fitur broadcast dinonaktifkan: tidak ada yang dikirim.');

            return self::SUCCESS;
        }

        $processed = $dispatchService->dispatchDue();

        $this->info('Dispatched '.count($processed).' broadcast(s).');

        return self::SUCCESS;
    }
}

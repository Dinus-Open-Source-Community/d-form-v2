<?php

namespace App\Console\Commands;

use App\Services\Recruitment\RecruitmentQrDispatchRecovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchRecruitmentQrOutboxCommand extends Command
{
    protected $signature = 'recruitment:dispatch-qr-outbox';

    protected $description = 'Recover and dispatch pending recruitment QR notifications';

    /** Dispatch pending QR intents, re-queue intents whose queue job was lost, and report the rows an operator must reconcile: `sending` rows are never resent automatically. */
    public function handle(RecruitmentQrDispatchRecovery $recovery): int
    {
        $dispatched = $recovery->dispatchPending();

        $this->info("Dispatched {$dispatched} recruitment QR notification(s).");

        $staleSending = $recovery->staleSendingCount();

        if ($staleSending > 0) {
            Log::warning('recruitment_qr_outbox_stale_sending', ['count' => $staleSending]);
            $this->warn(
                "{$staleSending} QR intent(s) stuck in sending; reconcile with "
                ."'recruitment:reconcile-qr-outbox {emailLogId} {sent|failed}'."
            );
        }

        $staleQueued = $recovery->staleQueuedCount();

        if ($staleQueued > 0) {
            Log::warning('recruitment_qr_outbox_stale_queued', ['count' => $staleQueued]);
            $this->warn("{$staleQueued} QR intent(s) queued past the grace window; their queue job was lost.");
        }

        $requeued = $recovery->repushStaleQueued();

        if ($requeued > 0) {
            Log::warning('recruitment_qr_outbox_stale_queued_requeued', ['count' => $requeued]);
            $this->info("Re-queued {$requeued} lost recruitment QR notification(s).");
        }

        return self::SUCCESS;
    }
}

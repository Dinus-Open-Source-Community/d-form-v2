<?php

namespace Tests\Feature\Broadcasting;

use App\Enums\EmailBroadcastRecipientStatus;
use App\Enums\EmailBroadcastStatus;
use App\Jobs\Broadcasting\SendBroadcastRecipientJob;
use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
use App\Services\Broadcasting\BroadcastDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BroadcastDispatchClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.broadcast' => true]);
        Queue::fake();
    }

    /**
     * Membuat broadcast scheduled beserta penerima dengan status tertentu.
     *
     * @param  array<int, EmailBroadcastRecipientStatus>  $recipientStatuses
     */
    private function scheduledBroadcast(array $recipientStatuses): EmailBroadcast
    {
        $broadcast = EmailBroadcast::query()->create([
            'name' => 'Claim test',
            'scheduled_at' => now()->subMinute(),
            'delay_min' => 0,
            'delay_max' => 0,
            'subject' => 'Halo {{name}}',
            'content' => '<p>Halo {{name}}</p>',
            'status' => EmailBroadcastStatus::Scheduled,
        ]);

        foreach ($recipientStatuses as $index => $status) {
            EmailBroadcastRecipient::query()->create([
                'broadcast_id' => $broadcast->id,
                'name' => 'Penerima '.$index,
                'email' => 'penerima'.$index.'@example.test',
                'status' => $status,
            ]);
        }

        return $broadcast;
    }

    /**
     * Mensimulasikan worker lain yang lebih dulu memenangkan claim:
     * tepat sebelum UPDATE claim milik service dieksekusi, status baris diubah
     * menjadi processing pada koneksi yang sama.
     */
    private function claimOnBehalfOfOtherWorker(string $broadcastId): void
    {
        $alreadyClaimed = false;

        DB::connection()->beforeExecuting(
            function (string $query, array $bindings) use (&$alreadyClaimed, $broadcastId): void {
                if ($alreadyClaimed) {
                    return;
                }

                if (! str_starts_with(ltrim($query), 'update')) {
                    return;
                }

                if (! str_contains($query, 'email_broadcasts')) {
                    return;
                }

                if (! in_array($broadcastId, $bindings, true)) {
                    return;
                }

                if (! in_array(EmailBroadcastStatus::Processing->value, $bindings, true)) {
                    return;
                }

                $alreadyClaimed = true;

                DB::table('email_broadcasts')
                    ->where('id', $broadcastId)
                    ->update([
                        'status' => EmailBroadcastStatus::Processing->value,
                        'started_at' => now(),
                    ]);
            }
        );
    }

    public function test_dispatch_claims_scheduled_broadcast_and_queues_only_pending_recipients(): void
    {
        $broadcast = $this->scheduledBroadcast([
            EmailBroadcastRecipientStatus::Pending,
            EmailBroadcastRecipientStatus::Pending,
            EmailBroadcastRecipientStatus::Sent,
        ]);

        $claimed = app(BroadcastDispatchService::class)->dispatch($broadcast);

        $this->assertTrue($claimed);
        $this->assertSame(EmailBroadcastStatus::Processing, $broadcast->refresh()->status);
        $this->assertNotNull($broadcast->started_at);
        Queue::assertPushed(SendBroadcastRecipientJob::class, 2);
    }

    public function test_dispatch_aborts_when_another_worker_wins_the_claim(): void
    {
        $broadcast = $this->scheduledBroadcast([
            EmailBroadcastRecipientStatus::Pending,
            EmailBroadcastRecipientStatus::Pending,
        ]);

        $this->claimOnBehalfOfOtherWorker($broadcast->id);

        $claimed = app(BroadcastDispatchService::class)->dispatch($broadcast);

        Queue::assertNothingPushed();
        $this->assertFalse($claimed);
        $this->assertSame(EmailBroadcastStatus::Processing, $broadcast->refresh()->status);
    }

    public function test_dispatch_twice_queues_recipients_only_once(): void
    {
        $broadcast = $this->scheduledBroadcast([
            EmailBroadcastRecipientStatus::Pending,
            EmailBroadcastRecipientStatus::Pending,
        ]);

        $service = app(BroadcastDispatchService::class);

        $this->assertTrue($service->dispatch($broadcast));
        $this->assertFalse($service->dispatch($broadcast));

        Queue::assertPushed(SendBroadcastRecipientJob::class, 2);
    }

    public function test_dispatch_completes_broadcast_without_pending_recipients(): void
    {
        $broadcast = $this->scheduledBroadcast([
            EmailBroadcastRecipientStatus::Sent,
        ]);

        $claimed = app(BroadcastDispatchService::class)->dispatch($broadcast);

        $this->assertTrue($claimed);
        $this->assertSame(EmailBroadcastStatus::Completed, $broadcast->refresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_dispatch_due_reports_only_broadcasts_claimed_by_this_worker(): void
    {
        $claimedHere = $this->scheduledBroadcast([EmailBroadcastRecipientStatus::Pending]);
        $claimedElsewhere = $this->scheduledBroadcast([EmailBroadcastRecipientStatus::Pending]);

        $this->claimOnBehalfOfOtherWorker($claimedElsewhere->id);

        $processed = app(BroadcastDispatchService::class)->dispatchDue();

        $this->assertContains($claimedHere->id, $processed);
        $this->assertNotContains($claimedElsewhere->id, $processed);
        Queue::assertPushed(SendBroadcastRecipientJob::class, 1);
    }

    public function test_dispatch_ignores_broadcast_that_is_not_scheduled(): void
    {
        $broadcast = $this->scheduledBroadcast([EmailBroadcastRecipientStatus::Pending]);
        $broadcast->forceFill(['status' => EmailBroadcastStatus::Cancelled])->save();

        $claimed = app(BroadcastDispatchService::class)->dispatch($broadcast->refresh());

        $this->assertFalse($claimed);
        Queue::assertNothingPushed();
    }

    public function test_retry_failed_requeues_each_failed_recipient_once(): void
    {
        $broadcast = $this->scheduledBroadcast([EmailBroadcastRecipientStatus::Failed]);
        $broadcast->forceFill([
            'status' => EmailBroadcastStatus::Completed,
            'completed_at' => now(),
        ])->save();

        $service = app(BroadcastDispatchService::class);

        $this->assertSame(1, $service->retryFailed($broadcast));
        $this->assertSame(0, $service->retryFailed($broadcast->refresh()));

        Queue::assertPushed(SendBroadcastRecipientJob::class, 1);
    }

    public function test_retry_failed_aborts_when_another_caller_resets_the_recipient(): void
    {
        $broadcast = $this->scheduledBroadcast([EmailBroadcastRecipientStatus::Failed]);
        $broadcast->forceFill([
            'status' => EmailBroadcastStatus::Completed,
            'completed_at' => now(),
        ])->save();

        $recipientId = (string) $broadcast->recipients()->firstOrFail()->id;

        $alreadyReset = false;

        DB::connection()->beforeExecuting(
            function (string $query, array $bindings) use (&$alreadyReset, $recipientId): void {
                if ($alreadyReset) {
                    return;
                }

                if (! str_starts_with(ltrim($query), 'update')) {
                    return;
                }

                if (! str_contains($query, 'email_broadcast_recipients')) {
                    return;
                }

                if (! in_array($recipientId, $bindings, true)) {
                    return;
                }

                if (! in_array(EmailBroadcastRecipientStatus::Pending->value, $bindings, true)) {
                    return;
                }

                $alreadyReset = true;

                DB::table('email_broadcast_recipients')
                    ->where('id', $recipientId)
                    ->update(['status' => EmailBroadcastRecipientStatus::Pending->value]);
            }
        );

        $retried = app(BroadcastDispatchService::class)->retryFailed($broadcast->refresh());

        Queue::assertNothingPushed();
        $this->assertSame(0, $retried);
    }
}

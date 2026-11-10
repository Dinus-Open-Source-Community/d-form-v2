<?php

namespace Tests\Feature\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Models\EmailBroadcast;
use App\Models\EmailDataset;
use App\Services\Broadcasting\BroadcastDatasetResolver;
use App\Services\Broadcasting\BroadcastSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * EP-04 AUDIT-P2-009: streaming snapshot must keep recipient order, duplicate
 * accounting, and inserted rows identical while capping the duplicate sample.
 */
class BroadcastSnapshotStreamingTest extends TestCase
{
    use RefreshDatabase;

    private function broadcast(): EmailBroadcast
    {
        return EmailBroadcast::query()->create([
            'name' => 'Stream',
            'scheduled_at' => now()->addDay(),
            'delay_min' => 0,
            'delay_max' => 0,
            'status' => EmailBroadcastStatus::Draft,
            'created_by' => null,
        ]);
    }

    /**
     * @param  list<array{name:string|null, email:string}>  $rows
     */
    private function customDataset(array $rows): EmailDataset
    {
        $dataset = EmailDataset::query()->create([
            'name' => 'Stream dataset',
            'source_type' => 'custom',
            'created_by' => null,
        ]);

        $records = array_map(fn (array $row): array => [
            'id' => (string) Str::uuid(),
            'dataset_id' => $dataset->id,
            'name' => $row['name'],
            'email' => $row['email'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $rows);

        $dataset->recipients()->insert($records);

        return $dataset;
    }

    /**
     * @return list<string>
     */
    private function insertedEmailsInOrder(): array
    {
        return DB::table('email_broadcast_recipients')
            ->orderByRaw('rowid')
            ->pluck('email')
            ->all();
    }

    public function test_streaming_keeps_row_order_and_duplicate_accounting(): void
    {
        $broadcast = $this->broadcast();
        $dataset = $this->customDataset([
            ['name' => 'A', 'email' => 'a@example.com'],
            ['name' => 'B', 'email' => 'b@example.com'],
            ['name' => 'A2', 'email' => 'a@example.com'],
        ]);

        $summary = app(BroadcastSnapshotService::class)->generate($broadcast, [
            ['type' => 'custom', 'id' => $dataset->id],
        ], [
            ['name' => 'C', 'email' => 'c@example.com'],
            ['name' => 'A3', 'email' => 'a@example.com'],
            ['name' => 'Invalid', 'email' => 'not-an-email'],
        ]);

        // Resolver rows keep the resolver order; extra rows follow in input order.
        $resolvedOrder = array_map(
            static fn (array $row): string => $row['email'],
            app(BroadcastDatasetResolver::class)->resolve([['type' => 'custom', 'id' => $dataset->id]]),
        );
        $inserted = $this->insertedEmailsInOrder();

        $this->assertSame(5, $summary['total']);
        $this->assertSame(3, $summary['unique']);
        $this->assertSame(2, $summary['duplicates']);
        $this->assertSame(['a@example.com'], $summary['duplicate_emails']);
        $this->assertSame($resolvedOrder, array_slice($inserted, 0, count($resolvedOrder)));
        $this->assertSame(['c@example.com', 'a@example.com'], array_slice($inserted, count($resolvedOrder)));

        // array_count_values() keeps first-occurrence key order, so sort keys
        // before comparing: the multiset is the invariant, not encounter order.
        $insertedCounts = array_count_values($inserted);
        ksort($insertedCounts);

        $this->assertSame(
            ['a@example.com' => 3, 'b@example.com' => 1, 'c@example.com' => 1],
            $insertedCounts,
        );
        $this->assertSame(5, $broadcast->recipients()->count());
        $this->assertSame(5, $broadcast->refresh()->total_recipients);

        $aggregate = app(BroadcastSnapshotService::class)->duplicateSummary($broadcast);

        $this->assertSame(5, $aggregate['total']);
        $this->assertSame(3, $aggregate['unique']);
        $this->assertSame(2, $aggregate['duplicates']);
        $this->assertSame(['a@example.com'], $aggregate['duplicate_emails']);

        // Same input must reproduce the same snapshot row sequence.
        app(BroadcastSnapshotService::class)->generate($broadcast, [
            ['type' => 'custom', 'id' => $dataset->id],
        ], [
            ['name' => 'C', 'email' => 'c@example.com'],
            ['name' => 'A3', 'email' => 'a@example.com'],
            ['name' => 'Invalid', 'email' => 'not-an-email'],
        ]);

        $this->assertSame($inserted, $this->insertedEmailsInOrder());
    }

    public function test_duplicate_sample_is_capped(): void
    {
        $broadcast = $this->broadcast();
        $rows = [];

        for ($i = 0; $i < 60; $i++) {
            $rows[] = ['name' => 'Dup '.$i, 'email' => 'dup'.$i.'@example.com'];
            $rows[] = ['name' => 'Dup '.$i.' again', 'email' => 'dup'.$i.'@example.com'];
        }

        $summary = app(BroadcastSnapshotService::class)->generate($broadcast, [], $rows);

        $this->assertSame(120, $summary['total']);
        $this->assertSame(120 - 60, $summary['duplicates']);
        $this->assertCount(50, $summary['duplicate_emails']);

        $aggregate = app(BroadcastSnapshotService::class)->duplicateSummary($broadcast);

        $this->assertSame(60, $aggregate['duplicates']);
        $this->assertCount(50, $aggregate['duplicate_emails']);
    }
}

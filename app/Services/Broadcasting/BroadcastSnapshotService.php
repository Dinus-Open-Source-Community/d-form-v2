<?php

namespace App\Services\Broadcasting;

use App\Enums\EmailBroadcastRecipientStatus;
use App\Enums\EmailBroadcastStatus;
use App\Models\EmailBroadcast;
use Generator;
use Illuminate\Support\Facades\DB;

/** Generate snapshot recipient dari dataset terpilih + manual + CSV rows. */
class BroadcastSnapshotService
{
    private const INSERT_BATCH_SIZE = 500;

    /** Duplicate email list is a UI sample, never the full set. */
    private const DUPLICATE_SAMPLE_LIMIT = 50;

    public function __construct(
        private readonly BroadcastDatasetResolver $resolver,
    ) {
    }

    /**
     *
     * Stream resolved + extra rows into bounded batches instead of merging the whole dataset in memory.
     * @param  array<int, array{type:string, id?:string|null, event_id?:string|null, period_id?:string|null}>  $datasets
     * @param  array<int, array{name?:string|null, email:string}>  $extraRows manual/CSV yang sudah tervalidasi
     * @return array{total:int, unique:int, duplicates:int, duplicate_emails:array<int,string>}
     *
     */
    public function generate(EmailBroadcast $broadcast, array $datasets, array $extraRows = []): array
    {
        return DB::transaction(function () use ($broadcast, $datasets, $extraRows): array {
            // Hapus snapshot lama hanya saat masih draft (PRD: scheduled tidak boleh ubah recipient).
            $broadcast->recipients()->delete();

            $this->insertRecipients($broadcast, $datasets, $extraRows, now());

            $summary = $this->duplicateSummary($broadcast);

            $broadcast->forceFill([
                'datasets' => array_values($datasets),
                'total_recipients' => $summary['total'],
                'total_sent' => 0,
                'total_failed' => 0,
            ])->save();

            return $summary;
        });
    }

    /** @return array{total:int, unique:int, duplicates:int, duplicate_emails:array<int,string>} */
    public function duplicateSummary(EmailBroadcast $broadcast): array
    {
        $total = $broadcast->recipients()->count();
        $unique = $broadcast->recipients()->distinct()->count('email');

        $duplicateEmails = $broadcast->recipients()
            ->select('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('email')
            ->limit(self::DUPLICATE_SAMPLE_LIMIT)
            ->pluck('email')
            ->all();

        return [
            'total' => $total,
            'unique' => $unique,
            'duplicates' => $total - $unique,
            'duplicate_emails' => $duplicateEmails,
        ];
    }

    public function refreshCounters(EmailBroadcast $broadcast): void
    {
        $broadcast->forceFill([
            'total_recipients' => $broadcast->recipients()->count(),
            'total_sent' => $broadcast->recipients()->where('status', EmailBroadcastRecipientStatus::Sent->value)->count(),
            'total_failed' => $broadcast->recipients()->where('status', EmailBroadcastRecipientStatus::Failed->value)->count(),
        ])->save();
    }

    public function assertEditable(EmailBroadcast $broadcast): void
    {
        abort_unless($broadcast->status === EmailBroadcastStatus::Draft, 422, 'Snapshot hanya dapat diubah saat broadcast masih draft.');
    }

    /**
     *
     * @param  array<int, array{type:string, id?:string|null, event_id?:string|null, period_id?:string|null}>  $datasets
     * @param  array<int, array{name?:string|null, email:string}>  $extraRows
     *
     */
    private function insertRecipients(EmailBroadcast $broadcast, array $datasets, array $extraRows, \DateTimeInterface $now): void
    {
        $batch = [];

        foreach ($this->mergedRows($datasets, $extraRows) as $row) {
            $batch[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'broadcast_id' => $broadcast->id,
                'name' => $row['name'],
                'email' => $row['email'],
                'status' => EmailBroadcastRecipientStatus::Pending->value,
                'attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === self::INSERT_BATCH_SIZE) {
                $broadcast->recipients()->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $broadcast->recipients()->insert($batch);
        }
    }

    /**
     *
     * Merge resolver stream and extra rows in the historical order, dropping
     * invalid emails exactly like the previous array_merge + array_filter.
     *
     * @param  array<int, array{type:string, id?:string|null, event_id?:string|null, period_id?:string|null}>  $datasets
     * @param  array<int, array{name?:string|null, email:string}>  $extraRows
     * @return Generator<int, array{name:string|null, email:string}>
     *
     */
    private function mergedRows(array $datasets, array $extraRows): Generator
    {
        foreach ($this->resolver->stream($datasets) as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            yield ['name' => $row['name'] ?? null, 'email' => $email];
        }

        foreach ($extraRows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            yield ['name' => $row['name'] ?? null, 'email' => $email];
        }
    }
}

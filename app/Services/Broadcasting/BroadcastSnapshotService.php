<?php

namespace App\Services\Broadcasting;

use App\Enums\EmailBroadcastRecipientStatus;
use App\Enums\EmailBroadcastStatus;
use App\Models\EmailBroadcast;
use Illuminate\Support\Facades\DB;

/**
 * Generate snapshot recipient dari dataset terpilih + manual + CSV rows.
 * Snapshot adalah source of truth; dataset setelahnya tidak memengaruhi broadcast.
 */
class BroadcastSnapshotService
{
    public function __construct(
        private readonly BroadcastDatasetResolver $resolver,
    ) {
    }

    /**
     * @param  array<int, array{type:string, id?:string|null, event_id?:string|null, period_id?:string|null}>  $datasets
     * @param  array<int, array{name?:string|null, email:string}>  $extraRows manual/CSV yang sudah tervalidasi
     * @return array{total:int, unique:int, duplicates:int, duplicate_emails:array<int,string>}
     */
    public function generate(EmailBroadcast $broadcast, array $datasets, array $extraRows = []): array
    {
        $resolved = $this->resolver->resolve($datasets);

        $merged = array_merge(
            array_map(fn ($r) => ['name' => $r['name'] ?? null, 'email' => $r['email']], $resolved),
            array_map(fn ($r) => [
                'name' => $r['name'] ?? null,
                'email' => strtolower(trim((string) ($r['email'] ?? ''))),
            ], $extraRows),
        );

        // Filter email valid saja; invalid sudah ditolak di request layer.
        $merged = array_values(array_filter(
            $merged,
            fn ($r) => $r['email'] !== '' && filter_var($r['email'], FILTER_VALIDATE_EMAIL) !== false
        ));

        $summary = $this->resolver->duplicateSummary(array_column($merged, 'email'));

        DB::transaction(function () use ($broadcast, $datasets, $merged): void {
            // Hapus snapshot lama hanya saat masih draft (PRD: scheduled tidak boleh ubah recipient).
            $broadcast->recipients()->delete();

            $now = now();
            $chunks = array_chunk($merged, 500);

            foreach ($chunks as $chunk) {
                $rows = array_map(fn ($r) => [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'broadcast_id' => $broadcast->id,
                    'name' => $r['name'],
                    'email' => $r['email'],
                    'status' => EmailBroadcastRecipientStatus::Pending->value,
                    'attempts' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);

                $broadcast->recipients()->insert($rows);
            }

            $broadcast->forceFill([
                'datasets' => array_values($datasets),
                'total_recipients' => count($merged),
                'total_sent' => 0,
                'total_failed' => 0,
            ])->save();
        });

        return $summary;
    }

    /** @return array{total:int, unique:int, duplicates:int, duplicate_emails:array<int,string>} */
    public function duplicateSummary(EmailBroadcast $broadcast): array
    {
        $emails = $broadcast->recipients()->pluck('email')->all();

        return $this->resolver->duplicateSummary($emails);
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
}

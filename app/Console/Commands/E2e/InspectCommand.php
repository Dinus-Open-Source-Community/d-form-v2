<?php

namespace App\Console\Commands\E2e;

use App\Console\Commands\E2e\Concerns\ResolvesFixtureIdentity;
use App\Support\E2e\FixtureScope;
use Illuminate\Console\Command;

/**
 * Tampilkan identitas, id, dan jumlah baris milik satu marker.
 * Dipakai saat mendiagnosis sisa data tanpa query manual.
 */
final class InspectCommand extends Command
{
    use ResolvesFixtureIdentity;

    protected $signature = 'e2e:inspect
        {--marker= : Marker kanonik e2e+<runId>+o<12 hex>}
        {--run-id= : runId bila marker diturunkan dari ownerId}
        {--owner-id= : ownerId bila marker diturunkan dari ownerId}
        {--json : Cetak hasil sebagai JSON}';

    protected $description = 'Inspect marker-owned e2e rows (ids and counts).';

    public function handle(): int
    {
        $identity = $this->resolveIdentity();
        $scope = new FixtureScope($identity);
        $ids = $scope->ids();

        $this->line(json_encode([
            'marker' => $identity->marker,
            'runId' => $identity->runId,
            'ownerKey' => $identity->ownerKey,
            'slug' => $identity->slug(),
            'email' => $identity->email(),
            'ids' => $ids,
            'counts' => array_map(static fn (array $rows): int => count($rows), $ids),
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}

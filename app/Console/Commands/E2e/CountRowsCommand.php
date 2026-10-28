<?php

namespace App\Console\Commands\E2e;

use App\Console\Commands\E2e\Concerns\ResolvesFixtureIdentity;
use App\Support\E2e\FixtureScope;
use Illuminate\Console\Command;

/**
 * Hitung baris fixture milik satu marker (per tabel) tanpa predikat bebas.
 * Dipakai spec untuk assertion sebelum/sesudah mutasi.
 */
final class CountRowsCommand extends Command
{
    use ResolvesFixtureIdentity;

    protected $signature = 'e2e:count-rows
        {--marker= : Marker kanonik e2e+<runId>+o<12 hex>}
        {--run-id= : runId bila marker diturunkan dari ownerId}
        {--owner-id= : ownerId bila marker diturunkan dari ownerId}
        {--json : Cetak hasil sebagai JSON}';

    protected $description = 'Count marker-owned e2e rows per table.';

    public function handle(): int
    {
        $counts = (new FixtureScope($this->resolveIdentity()))->counts();
        $this->line(json_encode(['counts' => $counts, 'count' => array_sum($counts)], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}

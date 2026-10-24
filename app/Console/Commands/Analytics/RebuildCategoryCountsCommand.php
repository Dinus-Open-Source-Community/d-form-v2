<?php

namespace App\Console\Commands\Analytics;

use App\Services\Event\EventCache;
use App\Services\Event\EventCategoryCounter;
use Illuminate\Console\Command;

/**
 * Backfill/rekonsiliasi agregat `event_category_counts` dari tabel `events`. Wajib
 * dijalankan setelah migrate dan setelah `stress:seed`, karena seed memakai bulk query
 * yang tidak memicu EventObserver (jalur simpan normal sudah dijaga observer).
 */
class RebuildCategoryCountsCommand extends Command
{
    protected $signature = 'analytics:rebuild-category-counts';

    protected $description = 'Hitung ulang agregat kategori dashboard dari tabel events';

    public function handle(EventCategoryCounter $categoryCounter): int
    {
        $startedAt = hrtime(true);

        $result = $categoryCounter->rebuild();

        // Cache chart menyimpan hasil lama sampai TTL 6 jam; buster memaksa warm berikutnya.
        EventCache::bump(EventCache::BUSTER_CATEGORY);

        $milliseconds = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $this->info(
            "Agregat kategori dihitung ulang: {$result['tokens']} token dari {$result['events']} event ({$milliseconds} ms)."
        );

        return self::SUCCESS;
    }
}

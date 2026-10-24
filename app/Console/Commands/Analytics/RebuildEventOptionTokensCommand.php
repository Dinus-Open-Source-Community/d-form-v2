<?php

namespace App\Console\Commands\Analytics;

use App\Services\Event\EventCategoryOptionService;
use App\Services\Event\EventSessionOptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Isi ulang daftar token kategori/sesi untuk opsi filter. Satu-satunya tempat
 * yang boleh memindai `events`; jalankan setelah deploy/seed, bukan per request.
 */
class RebuildEventOptionTokensCommand extends Command
{
    protected $signature = 'analytics:rebuild-event-options';

    protected $description = 'Hitung ulang daftar opsi kategori/sesi dari tabel events (offline)';

    public function handle(): int
    {
        $categories = DB::table('events')
            ->whereNull('deleted_at')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->all();

        $sessions = DB::table('events')
            ->whereNull('deleted_at')
            ->whereNotNull('session')
            ->where('session', '!=', '')
            ->distinct()
            ->pluck('session')
            ->all();

        Cache::forever(EventCategoryOptionService::RAW_CACHE_KEY, $categories);
        Cache::forever(EventSessionOptionService::RAW_CACHE_KEY, $sessions);

        $this->info('Opsi filter disegarkan: '.count($categories).' baris kategori, '.count($sessions).' baris sesi.');

        return self::SUCCESS;
    }
}

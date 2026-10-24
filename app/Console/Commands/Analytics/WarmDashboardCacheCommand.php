<?php

namespace App\Console\Commands\Analytics;

use App\Services\Reporting\EventReportingQuery;
use Illuminate\Console\Command;

/**
 * Menghitung ulang cache agregat `/dashboard` di luar request path: statistik event,
 * jumlah pendaftar, dan breakdown kategori. Dijadwalkan tiap 5 menit (routes/console.php)
 * dan hanya menghitung kunci yang kosong, kecuali `--force`. Jalankan `--force` setelah
 * deploy/seed agar dashboard tidak menampilkan nol.
 */
class WarmDashboardCacheCommand extends Command
{
    protected $signature = 'dashboard:warm {--force : Hitung ulang walau cache masih terisi}';

    protected $description = 'Hitung ulang cache dashboard (statistik, pendaftar, kategori) tanpa membebani request';

    public function handle(EventReportingQuery $reportingQuery): int
    {
        $steps = $reportingQuery->warmDashboardCache((bool) $this->option('force'));

        foreach ($steps as $step) {
            $status = $step['skipped'] ? 'dilewati, cache terisi' : "{$step['milliseconds']} ms";

            $this->line("{$step['key']}: {$status} ({$step['rows']} baris)");
        }

        $totalMs = array_sum(array_column($steps, 'milliseconds'));
        $this->info('Cache dashboard siap: '.count($steps)." kunci, total {$totalMs} ms.");

        return self::SUCCESS;
    }
}

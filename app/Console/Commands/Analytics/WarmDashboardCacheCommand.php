<?php

namespace App\Console\Commands\Analytics;

use App\Services\Reporting\EventReportingQuery;
use Illuminate\Console\Command;

/**
 * Alat opsional untuk mengisi cache agregat `/dashboard` di luar request path (statistik
 * event, jumlah pendaftar, breakdown kategori). Request tetap bisa menghitung sendiri saat
 * cache kosong; command ini dipakai setelah deploy/seed agar pengunjung pertama tidak menunggu.
 * Kunci yang masih terisi dilewati kecuali `--force`.
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

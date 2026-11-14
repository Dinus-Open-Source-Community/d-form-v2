<?php

namespace App\Console\Commands\Analytics;

use App\Services\Reporting\EventReportingQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Alat opsional untuk mengisi cache agregat `/dashboard` di luar request path (statistik event, jumlah pendaftar, breakdown kategori). */
class WarmDashboardCacheCommand extends Command
{
    protected $signature = 'dashboard:warm
        {--force : Hitung ulang walau cache masih terisi}
        {--rebuild-trend : Paksa hitung ulang agregat harian dari sumber}';

    protected $description = 'Hitung ulang cache dashboard (statistik, pendaftar, kategori) tanpa membebani request';

    public function handle(EventReportingQuery $reportingQuery): int
    {
        $shouldRebuildTrend = (bool) $this->option('rebuild-trend')
            || ! DB::table('event_daily_registrations')->exists();

        if ($shouldRebuildTrend) {
            $result = $reportingQuery->rebuildDailyRegistrationAggregate();
            $this->line("agregat harian registrasi: {$result['total']} registrasi, {$result['days']} tanggal");
        }

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

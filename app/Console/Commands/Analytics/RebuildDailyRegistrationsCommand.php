<?php

namespace App\Console\Commands\Analytics;

use App\Services\Reporting\EventReportingQuery;
use Illuminate\Console\Command;

/**
 * Menghitung ulang agregat harian registrasi dari sumbernya. Dipakai sekali setelah
 * seed/import massal (jalur yang melewati FormAnswerObserver) dan untuk rekonsiliasi.
 * Idempoten: aman dijalankan berulang; tidak mengubah tabel sumber.
 *
 * Jalankan di luar jam sibuk. Di data 1 juta `form_answers` satu pass memakan ±2 s;
 * selama proses berjalan, request `/dashboard` tetap memakai cache fallback.
 */
class RebuildDailyRegistrationsCommand extends Command
{
    protected $signature = 'analytics:rebuild-daily-registrations';

    protected $description = 'Hitung ulang tabel event_daily_registrations dari form_answers (offline, idempoten)';

    public function handle(EventReportingQuery $reportingQuery): int
    {
        $result = $reportingQuery->rebuildDailyRegistrationAggregate();

        $this->info(
            "Agregat harian registrasi dihitung ulang: {$result['total']} registrasi pada {$result['days']} tanggal. "
            .'Cache fallback sumber sudah diinvalidasi; agregat ini yang dipakai /dashboard.'
        );

        return self::SUCCESS;
    }
}

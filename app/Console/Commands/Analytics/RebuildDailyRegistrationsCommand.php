<?php

namespace App\Console\Commands\Analytics;

use App\Services\Reporting\EventReportingQuery;
use Illuminate\Console\Command;

/** Menghitung ulang agregat harian registrasi dari sumbernya. */
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

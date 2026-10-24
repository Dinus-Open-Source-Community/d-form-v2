<?php

namespace App\Console\Commands\Analytics;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung ulang agregat harian registrasi dari sumbernya. Dipakai untuk
 * rekonsiliasi setelah event/form dihapus (drift yang tidak tertangkap observer).
 * Offline: agregat normal dijaga FormAnswerObserver, bukan command ini.
 */
class RebuildDailyRegistrationsCommand extends Command
{
    protected $signature = 'analytics:rebuild-daily-registrations';

    protected $description = 'Hitung ulang tabel event_daily_registrations dari form_answers';

    public function handle(): int
    {
        DB::transaction(function (): void {
            DB::table('event_daily_registrations')->delete();

            DB::statement(
                'insert into event_daily_registrations (registration_date, `count`) '
                .'select date(fa.created_at), count(fa.id) '
                .'from form_answers fa '
                .'inner join forms f on f.id = fa.form_id '
                .'inner join events e on e.id = f.event_id '
                .'where e.deleted_at is null and f.deleted_at is null '
                .'group by date(fa.created_at)'
            );
        });

        $total = (int) DB::table('event_daily_registrations')->sum('count');
        $days = (int) DB::table('event_daily_registrations')->count();
        $this->info("Agregat harian registrasi dihitung ulang: {$total} registrasi pada {$days} tanggal.");

        return self::SUCCESS;
    }
}

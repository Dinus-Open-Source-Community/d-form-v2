<?php

namespace App\Observers;

use App\Models\FormAnswer;
use Illuminate\Support\Facades\DB;

/**
 * Menjaga agregat harian `event_daily_registrations` tetap sinkron saat satu
 * submisi dibuat/dihapus (bukan jalur massal/seed).
 */
class FormAnswerObserver
{
    /** Tambah 1 pada tanggal submisi dibuat. */
    public function created(FormAnswer $answer): void
    {
        $this->changeCount($answer, 1);
    }

    /** Kurangi 1 pada tanggal submisi dihapus, tanpa membuat nilai negatif. */
    public function deleted(FormAnswer $answer): void
    {
        $this->changeCount($answer, -1);
    }

    /** Upsert (atau decrement) satu baris agregat untuk tanggal submisi. */
    private function changeCount(FormAnswer $answer, int $delta): void
    {
        $date = ($answer->created_at ?? now())->toDateString();

        if ($delta > 0) {
            DB::table('event_daily_registrations')->upsert(
                [['registration_date' => $date, 'count' => $delta]],
                ['registration_date'],
                ['count' => DB::raw('count + '.$delta)],
            );

            return;
        }

        DB::table('event_daily_registrations')
            ->where('registration_date', $date)
            ->where('count', '>', 0)
            ->decrement('count', abs($delta));
    }
}

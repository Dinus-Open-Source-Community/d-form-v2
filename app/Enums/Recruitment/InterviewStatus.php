<?php

namespace App\Enums\Recruitment;

enum InterviewStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case CheckedIn = 'checked_in';
    case Queued = 'queued';
    case Called = 'called';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal',
            self::Cancelled => 'Dibatalkan',
            self::CheckedIn => 'Check-in',
            self::Queued => 'Antrean',
            self::Called => 'Dipanggil',
            self::InProgress => 'Berlangsung',
            self::Completed => 'Selesai',
            self::NoShow => 'Tidak hadir',
        };
    }
}

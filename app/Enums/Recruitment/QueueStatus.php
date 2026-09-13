<?php

namespace App\Enums\Recruitment;

enum QueueStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu',
            self::Called => 'Dipanggil',
            self::InProgress => 'Berlangsung',
            self::Completed => 'Selesai',
        };
    }
}

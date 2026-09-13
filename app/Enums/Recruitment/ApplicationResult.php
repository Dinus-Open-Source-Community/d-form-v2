<?php

namespace App\Enums\Recruitment;

enum ApplicationResult: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu keputusan',
            self::Accepted => 'Diterima',
            self::Rejected => 'Tidak diterima',
            self::Cancelled => 'Dibatalkan',
        };
    }
}

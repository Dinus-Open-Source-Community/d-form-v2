<?php

namespace App\Enums;

enum EmailBroadcastStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function canEditContent(): bool
    {
        return $this === self::Draft;
    }

    public function canEditSchedule(): bool
    {
        return $this === self::Scheduled;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Scheduled, self::Processing], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}

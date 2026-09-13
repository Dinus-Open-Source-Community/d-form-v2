<?php

namespace App\Enums\Recruitment;

enum ScreeningDecision: string
{
    case Pass = 'pass';
    case RevisionRequired = 'revision_required';
    case Reject = 'reject';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'Lolos screening',
            self::RevisionRequired => 'Perlu revisi',
            self::Reject => 'Ditolak',
        };
    }
}

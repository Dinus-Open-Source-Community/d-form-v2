<?php

namespace App\Enums\Recruitment;

enum ApplicationStage: string
{
    case Submitted = 'submitted';
    case Screening = 'screening';
    case Interview = 'interview';
    case FinalReview = 'final_review';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Pendaftaran diterima',
            self::Screening => 'Screening',
            self::Interview => 'Interview',
            self::FinalReview => 'Final review',
            self::Completed => 'Selesai',
        };
    }

    /**
     * @return list<self>
     */
    public static function timelineOrder(): array
    {
        return [
            self::Submitted,
            self::Screening,
            self::Interview,
            self::FinalReview,
            self::Completed,
        ];
    }
}

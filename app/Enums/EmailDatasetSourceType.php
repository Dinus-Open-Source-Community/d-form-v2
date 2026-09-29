<?php

namespace App\Enums;

enum EmailDatasetSourceType: string
{
    case EventParticipants = 'event_participants';
    case RecruitmentApplicants = 'recruitment_applicants';
    case Users = 'users';
    case Custom = 'custom';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}

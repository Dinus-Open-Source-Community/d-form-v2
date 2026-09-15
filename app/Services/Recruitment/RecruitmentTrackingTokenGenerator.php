<?php

namespace App\Services\Recruitment;

final class RecruitmentTrackingTokenGenerator
{
    private const LENGTH = 8;

    public function generate(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $pool = $letters.$digits;

        $characters = [
            $letters[random_int(0, strlen($letters) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];

        for ($i = count($characters); $i < self::LENGTH; $i++) {
            $characters[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        shuffle($characters);

        return implode('', $characters);
    }
}

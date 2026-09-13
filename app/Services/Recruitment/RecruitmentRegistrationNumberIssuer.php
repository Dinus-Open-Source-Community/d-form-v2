<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentRegistrationSequence;
use Illuminate\Support\Facades\DB;

final class RecruitmentRegistrationNumberIssuer
{
    public function issue(RecruitmentPeriod $period): string
    {
        return DB::transaction(function () use ($period): string {
            $sequence = RecruitmentRegistrationSequence::query()
                ->where('recruitment_period_id', $period->id)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = RecruitmentRegistrationSequence::query()->create([
                    'recruitment_period_id' => $period->id,
                    'last_sequence' => 0,
                ]);

                $sequence = RecruitmentRegistrationSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->increment('last_sequence');
            $sequence->refresh();

            $year = $period->registration_opens_at?->year
                ?? $period->created_at?->year
                ?? now()->year;

            return sprintf('OPREC-%d-%05d', $year, $sequence->last_sequence);
        });
    }
}

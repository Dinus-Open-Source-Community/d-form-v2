<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
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

            $year = $period->registration_opens_at?->year
                ?? $period->created_at?->year
                ?? now()->year;

            // Sequence tetap per-periode, tetapi nomor bersifat unik global.
            // Periode lain pada tahun yang sama bisa sudah memakai nomor yang
            // sama (sequence masing-masing mulai dari 0), jadi lewati nomor
            // yang sudah dipakai periode lain hingga menemukan yang bebas.
            do {
                $sequence->increment('last_sequence');
                $sequence->refresh();

                $registrationNumber = sprintf('OPREC-%d-%05d', $year, $sequence->last_sequence);
            } while ($this->isNumberUsedByAnotherPeriod($registrationNumber, $period));

            return $registrationNumber;
        });
    }

    private function isNumberUsedByAnotherPeriod(string $registrationNumber, RecruitmentPeriod $period): bool
    {
        return RecruitmentApplication::query()
            ->where('registration_number', $registrationNumber)
            ->where('recruitment_period_id', '!=', $period->id)
            ->exists();
    }
}

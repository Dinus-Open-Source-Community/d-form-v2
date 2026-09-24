<?php

namespace Database\Seeders;

use App\Enums\Recruitment\RecruitmentPeriodStatus;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentRegistrationSequence;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RecruitmentPeriodSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $period = RecruitmentPeriod::query()->firstOrCreate(
            ['slug' => 'oprec-2026'],
            [
                'name' => 'OpRec 2026',
                'status' => RecruitmentPeriodStatus::Open,
                'description' => 'Periode Open Recruitment DOSCOM 2026.',
                'registration_opens_at' => now()->subDay(),
                'registration_closes_at' => now()->addMonth(),
            ],
        );

        RecruitmentRegistrationSequence::query()->firstOrCreate(
            ['recruitment_period_id' => $period->id],
            ['last_sequence' => 0],
        );
    }
}

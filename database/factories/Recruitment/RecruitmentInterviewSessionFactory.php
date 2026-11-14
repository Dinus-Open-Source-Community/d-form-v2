<?php

namespace Database\Factories\Recruitment;

use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 *
 * @extends Factory<RecruitmentInterviewSession>
 *
 */
class RecruitmentInterviewSessionFactory extends Factory
{
    protected $model = RecruitmentInterviewSession::class;

    public function definition(): array
    {
        return [
            'recruitment_period_id' => RecruitmentPeriod::factory(),
            'recruitment_division_id' => RecruitmentDivision::factory(),
            'session_date' => today()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ];
    }
}

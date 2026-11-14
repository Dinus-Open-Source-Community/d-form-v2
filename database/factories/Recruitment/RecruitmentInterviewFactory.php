<?php

namespace Database\Factories\Recruitment;

use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 *
 * @extends Factory<RecruitmentInterview>
 *
 */
class RecruitmentInterviewFactory extends Factory
{
    protected $model = RecruitmentInterview::class;

    public function definition(): array
    {
        return [
            'recruitment_application_id' => RecruitmentApplication::factory(),
            'recruitment_interview_session_id' => RecruitmentInterviewSessionFactory::new(),
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'interviewer_id' => null,
            'scheduled_at' => now()->subHour(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => InterviewStatus::Waiting,
        ];
    }

    /** Interview sekunder untuk aplikasi yang sama. */
    public function secondary(): static
    {
        return $this->state(fn (): array => [
            'interview_kind' => RecruitmentInterview::KIND_SECONDARY,
        ]);
    }
}

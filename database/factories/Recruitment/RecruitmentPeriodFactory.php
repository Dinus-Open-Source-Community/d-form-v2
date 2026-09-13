<?php

namespace Database\Factories\Recruitment;

use App\Enums\Recruitment\RecruitmentPeriodStatus;
use App\Models\Recruitment\RecruitmentPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RecruitmentPeriod>
 */
class RecruitmentPeriodFactory extends Factory
{
    protected $model = RecruitmentPeriod::class;

    public function definition(): array
    {
        $name = 'OpRec '.$this->faker->year();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numerify('###'),
            'status' => RecruitmentPeriodStatus::Draft,
            'description' => $this->faker->paragraph(),
            'registration_opens_at' => now()->addWeek(),
            'registration_closes_at' => now()->addMonths(2),
            'interview_starts_at' => now()->addMonths(2)->toDateString(),
            'interview_ends_at' => now()->addMonths(3)->toDateString(),
            'finalization_deadline_at' => now()->addMonths(4)->toDateString(),
            'landing_content' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => RecruitmentPeriodStatus::Open,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addMonth(),
        ]);
    }
}

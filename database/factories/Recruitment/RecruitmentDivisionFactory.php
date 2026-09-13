<?php

namespace Database\Factories\Recruitment;

use App\Models\Recruitment\RecruitmentDivision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RecruitmentDivision>
 */
class RecruitmentDivisionFactory extends Factory
{
    protected $model = RecruitmentDivision::class;

    public function definition(): array
    {
        $code = Str::slug($this->faker->unique()->word());

        return [
            'code' => $code,
            'name' => ucfirst($code),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}

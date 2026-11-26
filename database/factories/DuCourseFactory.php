<?php

namespace Database\Factories;

use App\Models\DuCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DuCourse>
 */
class DuCourseFactory extends Factory
{
    protected $model = DuCourse::class;

    public function definition(): array
    {
        return [
            'uid' => fake()->unique()->bothify('course-####'),
            'title' => fake()->words(3, true),
            'is_active' => true,
            'last_synced_at' => now(),
        ];
    }
}

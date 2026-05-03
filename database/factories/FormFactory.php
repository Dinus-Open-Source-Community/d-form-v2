<?php

namespace Database\Factories;

use App\Enums\EventFormVisibility;
use App\Models\Event;
use App\Models\Form;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
            'event_id' => Event::factory(),
            'banner_url' => null,
            'banner_caption' => null,
        ];
    }
}

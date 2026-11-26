<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSession>
 */
class FormSessionFactory extends Factory
{
    protected $model = FormSession::class;

    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'session_date' => fake()->date(),
            'label' => fake()->sentence(3),
        ];
    }
}

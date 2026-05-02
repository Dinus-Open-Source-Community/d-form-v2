<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormAnswer>
 */
class FormAnswerFactory extends Factory
{
    protected $model = FormAnswer::class;

    public function definition(): array
    {
        return [
            'answers' => [],
            'form_id' => Form::factory(),
            'user_id' => User::factory(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\FormField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'type' => 'input', // Default
            'label' => fake()->words(2, true),
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'metadata' => [
                'rules' => [
                    'required' => true,
                    'min' => 10,
                    'max' => 255,
                    'regex' => '^adalah$'
                ],
                'placeholder' => 'Enter text...',
                'type' => 'text'
            ],
            'form_id' => fake()->uuid(),
            'order' => fake()->numberBetween(1, 10),
        ];
    }

    public function emailInput(): static
    {
        return $this->state(fn (array $attributes) => [
            'id' => fake()->uuid(),
            'type' => 'input', // Default
            'label' => fake()->words(2, true),
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'metadata' => [
                'rules' => [
                    'required' => true,
                    'min' => 10,
                    'max' => 255,
                ],
                'placeholder' => 'Enter text...',
                'type' => 'email'
            ],
            'form_id' => fake()->uuid(),
            'order' => fake()->numberBetween(1, 10),
        ]);
    }

    /** States untuk berbagai jenis input */

    public function selectInput(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'selectInput',
            'metadata' => [
                'rules' => [
                    'required' => true,
                    'in' => 'opsi1,opsi2,opsi3'
                ],
                'options' => [
                    ['label' => 'Option 1', 'value' => 'option1'],
                    ['label' => 'Option 2', 'value' => 'option2'],
                ],
            ],
        ]);
    }

    public function textarea(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'textarea',
            'metadata' => [
                'rules' => [
                    'required' => false,
                ]
            ],
        ]);
    }

    public function datePicker(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'datePicker',
            'metadata' => [
                'rules' => [
                    'max_date' => '2026-10-10'
                ]
            ],
        ]);
    }

    public function fileUpload(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fileUpload',
            'metadata' => [
                'rules' => [
                    'required' => true,
                    'max_size' => 2048,
                    'mimes' => 'jpg'
                ],
            ],
        ]);
    }
}

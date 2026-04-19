<?php

namespace Database\Factories;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startDate = now()->addDays(fake()->numberBetween(-30, 60));

        return [
            'title' => fake()->sentence(3),
            'description' => '<p>' . fake()->paragraph(3) . '</p>',
            'start_date' => $startDate->toDateString(),
            'end_date' => $startDate->copy()->addDays(fake()->numberBetween(0, 3))->toDateString(),
            'registration_start' => $startDate->copy()->subDays(14),
            'registration_end' => $startDate->copy()->subDays(2),
            'location' => fake()->streetAddress(),
            'quota' => fake()->numberBetween(50, 500),
            'registered_count' => 0,
            'banner' => 'events/banners/placeholder.jpg',
            'price' => fake()->randomElement([0, 10000, 25000, 50000, 75000]),
            'session' => fake()->randomElement(EventSession::cases())->value,
            'status' => fake()->randomElement(EventStatus::cases()),
            'category' => fake()->randomElement(EventCategory::cases())->value,
        ];
    }
}

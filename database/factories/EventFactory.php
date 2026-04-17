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
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'registration_start' => now(),
            'registration_end' => now()->addDays(5),
            'location' => fake()->streetAddress(),
            'quota' => 50,
            'registered_count' => 0,
            'banner' => 'events/banners/placeholder.jpg',
            'price' => 10000,
            'session' => EventSession::General,
            'status' => EventStatus::Draft,
            'category' => EventCategory::RKT,
        ];
    }
}

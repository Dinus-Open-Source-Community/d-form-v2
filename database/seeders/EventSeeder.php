<?php

namespace Database\Seeders;

use App\Enums\EventSession;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventCategory;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $event_category_ids = EventCategory::all();

        $events = [
            [
                'title' => 'Oprec member',
                'description' => fake()->paragraph(4),
                'start_date' => now()->addDays(7),
                'end_date' => now()->addDays(10),
                'registration_start' => now(),
                'registration_end' => now()->addDays(5),
                'location' => "UDINUS H.4",
                'quota' => 50,
                'banner' => "",
                'price' => 20000,
                'session' => EventSession::General,
                'status' => EventStatus::Draft,
                'event_category_id' => $event_category_ids[0]->id
            ],
            [
                'title' => 'Oprec member',
                'description' => fake()->paragraph(4),
                'start_date' => now()->addDays(7),
                'end_date' => now()->addDays(10),
                'registration_start' => now(),
                'registration_end' => now()->addDays(5),
                'location' => "UDINUS H.4",
                'quota' => 50,
                'banner' => "",
                'price' => 20000,
                'session' => EventSession::General,
                'status' => EventStatus::Published,
                'event_category_id' => $event_category_ids[1]->id
            ],
            [
                'title' => 'Oprec member',
                'description' => fake()->paragraph(4),
                'start_date' => now()->addDays(7),
                'end_date' => now()->addDays(10),
                'registration_start' => now(),
                'registration_end' => now()->addDays(5),
                'location' => "UDINUS H.4",
                'quota' => 50,
                'banner' => "",
                'price' => 20000,
                'session' => EventSession::General,
                'status' => EventStatus::Draft,
                'event_category_id' => $event_category_ids[2]->id
            ],
        ];

        collect($events)->each(fn ($event) => Event::create($event));
    }
}

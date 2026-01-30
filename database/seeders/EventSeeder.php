<?php

namespace Database\Seeders;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
                'banner' => "events/banners/dummy.webp",
                'price' => 20000,
                'session' => EventSession::General,
                'status' => EventStatus::Draft,
                'category' => EventCategory::RECRUITMENT
            ],
            [
                'title' => 'Opensource On The School',
                'description' => fake()->paragraph(5),
                'start_date' => now()->addDays(31),
                'end_date' => now()->addDays(35),
                'registration_start' => now()->addDays(31),
                'registration_end' => now()->addDays(35),
                'location' => "UDINUS D.2",
                'quota' => 100,
                'banner' => "events/banners/dummy.webp",
                'price' => 20000,
                'session' => EventSession::General,
                'status' => EventStatus::Published,
                'category' => EventCategory::RKT
            ],
            [
                'title' => 'Doscom University',
                'description' => fake()->paragraph(4),
                'start_date' => now()->addDays(100),
                'end_date' => now()->addDays(130),
                'registration_start' => now()->addDays(70),
                'registration_end' => now()->addDays(93),
                'location' => "UDINUS gedung H",
                'quota' => 150,
                'banner' => "events/banners/dummy.webp",
                'price' => 10000,
                'session' => EventSession::General,
                'status' => EventStatus::Draft,
                'category' => EventCategory::RKT
            ],
        ];

        collect($events)->each(fn ($event) => Event::create($event));
    }
}

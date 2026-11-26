<?php

namespace Tests\Feature\Database;

use App\Models\Event;
use App\Support\PublicStorage;
use Database\Seeders\EventSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kolom `events.banner` NOT NULL: seeder tidak boleh mengirim null, dan path placeholder
     * tidak boleh menjadi URL yang diumumkan saat berkasnya memang tidak ada.
     */
    public function test_event_seeder_fills_every_banner_without_publishing_a_missing_file(): void
    {
        $this->seed(EventSeeder::class);

        $this->assertGreaterThan(0, Event::query()->count());
        $this->assertSame(0, Event::query()->whereNull('banner')->count());

        foreach (Event::query()->pluck('banner') as $banner) {
            $this->assertNotSame('', (string) $banner);
            $this->assertNull(PublicStorage::existingUrl($banner));
        }
    }

    public function test_event_seeder_can_run_twice_without_duplicating_events(): void
    {
        $this->seed(EventSeeder::class);
        $countAfterFirstRun = Event::query()->count();

        $this->seed(EventSeeder::class);

        $this->assertSame($countAfterFirstRun, Event::query()->count());
    }
}

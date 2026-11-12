<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Services\Event\EventCategoryOptionService;
use App\Services\Event\EventSessionOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opsi filter kategori/sesi tidak boleh memindai tabel events per request.
 */
class EventOptionTokensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_opsi_filter_tidak_memindai_tabel_events(): void
    {
        DB::enableQueryLog();
        $categories = app(EventCategoryOptionService::class)->forForm();
        $sessions = app(EventSessionOptionService::class)->forForm();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
        DB::disableQueryLog();

        $this->assertStringNotContainsString('from "events"', $queries);
        $this->assertNotEmpty($categories);
        $this->assertNotEmpty($sessions);
    }

    public function test_command_rebuild_mengisi_cache_termasuk_token_kustom(): void
    {
        DB::table('events')->insert([
            'id' => (string) Str::uuid(),
            'title' => 'Acara Kustom',
            'slug' => 'acara-kustom-'.$this->uniqueSlug(),
            'description' => 'x',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'registration_start' => now(),
            'registration_end' => now()->addDay(),
            'location' => 'x',
            'quota' => 10,
            'registered_count' => 0,
            'banner' => '',
            'price' => 0,
            'session' => 'general',
            'status' => 'published',
            'category' => 'must',
        ]);

        $this->artisan('analytics:rebuild-event-options')->assertSuccessful();

        $values = collect(app(EventCategoryOptionService::class)->forForm())->pluck('value')->all();
        $this->assertContains('must', $values);
        $this->assertContains('rkt', $values);
    }

    public function test_observer_menambahkan_token_event_baru(): void
    {
        Event::factory()->create(['category' => 'etc', 'session' => 'data']);

        $this->assertContains('etc', Cache::get(EventCategoryOptionService::RAW_CACHE_KEY, []));
        $this->assertContains('data', Cache::get(EventSessionOptionService::RAW_CACHE_KEY, []));
    }

    private function uniqueSlug(): string
    {
        return (string) Str::uuid();
    }
}

<?php

namespace Tests\Feature\Reporting;

use App\Models\Event;
use App\Models\FormAnswer;
use App\Services\Event\EventCache;
use App\Services\Event\EventCategoryCounter;
use App\Services\Reporting\EventReportingQuery;
use Database\Seeders\EventSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardAnalyticsSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_event_seeder_rebuilds_category_counts_without_model_events(): void
    {
        Cache::put(EventCache::BUSTER_CATEGORY, 4);
        DB::table(EventCategoryCounter::TABLE)->insert([
            'category' => 'stale',
            'total' => 99,
        ]);

        Event::withoutEvents(function (): void {
            app(EventSeeder::class)->run();
        });

        $this->assertSame([
            'etc' => 1,
            'non-rkt' => 3,
            'recruitment' => 1,
            'rkt' => 4,
        ], DB::table(EventCategoryCounter::TABLE)
            ->orderBy('category')
            ->pluck('total', 'category')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all());
        $this->assertSame(5, Cache::get(EventCache::BUSTER_CATEGORY));
    }

    public function test_monthly_trend_falls_back_when_aggregate_has_no_rows_in_requested_range(): void
    {
        DB::table('event_daily_registrations')->insert([
            'registration_date' => now()->subMonths(7)->startOfMonth()->toDateString(),
            'count' => 99,
        ]);

        FormAnswer::withoutEvents(function (): void {
            FormAnswer::factory()->create([
                'created_at' => now()->startOfMonth()->addDay(),
            ]);
        });

        $trend = app(EventReportingQuery::class)->registrationTrendOverMonths(6);

        $this->assertSame(
            1,
            collect($trend)->firstWhere('key', now()->format('Y-m'))['count'],
        );
    }

    public function test_monthly_trend_falls_back_when_aggregate_has_only_future_rows(): void
    {
        DB::table('event_daily_registrations')->insert([
            'registration_date' => now()->startOfMonth()->addMonth()->toDateString(),
            'count' => 99,
        ]);

        FormAnswer::withoutEvents(function (): void {
            FormAnswer::factory()->create([
                'created_at' => now()->startOfMonth()->addDay(),
            ]);
        });

        $trend = app(EventReportingQuery::class)->registrationTrendOverMonths(6);

        $this->assertSame(
            1,
            collect($trend)->firstWhere('key', now()->format('Y-m'))['count'],
        );
    }
}

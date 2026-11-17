<?php

namespace App\Services\Reporting;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\Event\EventCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class EventReportingQuery
{
    /*
     * Kunci & TTL agregat dashboard: satu sumber untuk request path (`HomeController`)
     * dan command `dashboard:warm`. TTL panjang aman karena perubahan event menaikkan
     * buster masing-masing; request dilarang menghitung ulang saat cache kosong.
     */
    public const DASHBOARD_EVENT_STATS_KEY = 'dashboard:stats:events';

    public const DASHBOARD_REGISTRANTS_KEY = 'dashboard:stats:registrants';

    private const DASHBOARD_WEEKLY_KPI_KEY = 'dashboard:stats:weekly-kpi';

    private const DASHBOARD_EVENT_STATS_TTL_SECONDS = 900;

    private const DASHBOARD_REGISTRANTS_TTL_SECONDS = 60;

    private const DASHBOARD_WEEKLY_KPI_TTL_SECONDS = 300;

    /** Jumlah baris agregat per statement INSERT saat rebuild (tabel kecil, aman untuk MariaDB). */
    private const REBUILD_INSERT_CHUNK_SIZE = 500;

    /**
     * Isi ulang `event_daily_registrations` dari sumber. Idempoten: seluruh isi tabel
     * dihapus lalu ditulis per chunk dalam satu transaksi, sehingga aman dijalankan
     * berulang. Hanya untuk di luar request path (±2 s per 1 juta `form_answers`).
     *
     * @return array{total: int, days: int}
     */
    public function rebuildDailyRegistrationAggregate(): array
    {
        $rows = $this->dailyCountsFromSource();

        DB::transaction(function () use ($rows): void {
            DB::table('event_daily_registrations')->delete();

            foreach (array_chunk($rows, self::REBUILD_INSERT_CHUNK_SIZE) as $chunk) {
                DB::table('event_daily_registrations')->insert($chunk);
            }
        });

        return [
            'total' => array_sum(array_column($rows, 'count')),
            'days' => count($rows),
        ];
    }

    /**
     * Satu baris per tanggal submisi untuk seluruh riwayat.
     *
     * @return list<array{registration_date: string, count: int}>
     */
    private function dailyCountsFromSource(): array
    {
        $dateExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d', form_answers.created_at)"
            : 'DATE(form_answers.created_at)';

        return DB::table('form_answers')
            ->selectRaw("{$dateExpr} as registration_date")
            ->selectRaw('COUNT(*) as c')
            ->groupBy('registration_date')
            ->orderBy('registration_date')
            ->get()
            ->map(static fn (object $row): array => [
                'registration_date' => (string) $row->registration_date,
                'count' => (int) $row->c,
            ])
            ->all();
    }

    /**
     * Statistik event untuk `/dashboard`. Cache kosong → dihitung saat request
     * (sekali per TTL); `php artisan dashboard:warm` hanya alat opsional agar
     * pengunjung pertama setelah deploy tidak menunggu.
     *
     * @return array{totalEvents: int, activeEvents: int, completionRate: int}
     */
    public function dashboardEventStats(): array
    {
        return EventCache::remember(
            self::DASHBOARD_EVENT_STATS_KEY,
            self::DASHBOARD_EVENT_STATS_TTL_SECONDS,
            fn (): array => $this->computeDashboardEventStats(),
            EventCache::BUSTER_STATS,
        );
    }

    /** Total pendaftar untuk `/dashboard`; cache kosong → dihitung saat request, sekali per TTL. */
    public function dashboardRegistrantTotal(): int
    {
        $cached = EventCache::remember(
            self::DASHBOARD_REGISTRANTS_KEY,
            self::DASHBOARD_REGISTRANTS_TTL_SECONDS,
            fn (): array => $this->computeDashboardRegistrants(),
            EventCache::BUSTER_REGISTRANTS,
        );

        return (int) ($cached['total'] ?? 0);
    }

    /**
     * Weekly dashboard KPI buckets, including the current week.
     * Cohort quota uses current `registered_count` for events created in each bucket, not a historical snapshot.
     * @return array{
     *     totalEvents: list<array{label: string, value: int}>,
     *     activeEvents: list<array{label: string, value: int}>,
     *     totalRegistrants: list<array{label: string, value: int}>,
     *     completionRate: list<array{label: string, value: int}>
     * }
     */
    public function dashboardWeeklyKpiTrends(int $weeks = 6): array
    {
        $weeks = max(2, min(12, $weeks));

        return EventCache::remember(
            self::DASHBOARD_WEEKLY_KPI_KEY.':'.$weeks,
            self::DASHBOARD_WEEKLY_KPI_TTL_SECONDS,
            fn (): array => $this->computeDashboardWeeklyKpiTrends($weeks),
            null,
        );
    }

    /**
     * Compute event and quota cohorts in one `created_at`-bounded query.
     * @return array{
     * totalEvents: list<array{label: string, value: int}>,
     * activeEvents: list<array{label: string, value: int}>,
     * totalRegistrants: list<array{label: string, value: int}>,
     * completionRate: list<array{label: string, value: int}>
     * }
     *
     */
    private function computeDashboardWeeklyKpiTrends(int $weeks): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $currentWeekStart = CarbonImmutable::now($timezone)->startOfWeek(CarbonImmutable::MONDAY);
        $firstWeekStart = $currentWeekStart->subWeeks($weeks - 1);
        $endExclusive = $currentWeekStart->addWeek();
        $buckets = $this->weeklyBuckets($firstWeekStart, $weeks);
        $metrics = $this->weeklyEventMetrics($firstWeekStart, $endExclusive, $buckets);
        $registrationCounts = $this->weeklyRegistrationCounts($firstWeekStart, $endExclusive, $buckets);

        $trends = [
            'totalEvents' => [],
            'activeEvents' => [],
            'totalRegistrants' => [],
            'completionRate' => [],
        ];

        foreach ($buckets as $index => $bucket) {
            $registeredCount = $registrationCounts[$index] ?? 0;
            $quota = $metrics[$index]['quota'];
            $completionRate = $quota > 0
                ? min(100, (int) round(($metrics[$index]['registered'] / $quota) * 100))
                : 0;

            $trends['totalEvents'][] = ['label' => $bucket['label'], 'value' => $metrics[$index]['total']];
            $trends['activeEvents'][] = ['label' => $bucket['label'], 'value' => $metrics[$index]['active']];
            $trends['totalRegistrants'][] = ['label' => $bucket['label'], 'value' => $registeredCount];
            $trends['completionRate'][] = ['label' => $bucket['label'], 'value' => $completionRate];
        }

        return $trends;
    }

    /**
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, label: string}>
     *
     */
    private function weeklyBuckets(CarbonImmutable $firstWeekStart, int $weeks): array
    {
        $buckets = [];

        for ($index = 0; $index < $weeks; $index++) {
            $start = $firstWeekStart->addWeeks($index);

            $buckets[] = [
                'start' => $start,
                'end' => $start->addWeek(),
                'label' => $start->translatedFormat('d M'),
            ];
        }

        return $buckets;
    }

    /**
     *
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable, label: string}>  $buckets
     * @return array<int, array{total: int, active: int, registered: int, quota: int}>
     *
     */
    private function weeklyEventMetrics(
        CarbonImmutable $firstWeekStart,
        CarbonImmutable $endExclusive,
        array $buckets,
    ): array {
        $selects = [];
        $bindings = [];

        foreach ($buckets as $index => $bucket) {
            $selects[] = "SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS total_{$index}";
            $bindings[] = $bucket['start'];
            $bindings[] = $bucket['end'];

            $selects[] = "SUM(CASE WHEN created_at >= ? AND created_at < ? AND status = ? THEN 1 ELSE 0 END) AS active_{$index}";
            $bindings[] = $bucket['start'];
            $bindings[] = $bucket['end'];
            $bindings[] = EventStatus::Published->value;

            $selects[] = "SUM(CASE WHEN created_at >= ? AND created_at < ? AND status = ? THEN COALESCE(registered_count, 0) ELSE 0 END) AS registered_{$index}";
            $bindings[] = $bucket['start'];
            $bindings[] = $bucket['end'];
            $bindings[] = EventStatus::Published->value;

            $selects[] = "SUM(CASE WHEN created_at >= ? AND created_at < ? AND status = ? THEN COALESCE(quota, 0) ELSE 0 END) AS quota_{$index}";
            $bindings[] = $bucket['start'];
            $bindings[] = $bucket['end'];
            $bindings[] = EventStatus::Published->value;
        }

        $row = Event::query()
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $firstWeekStart)
            ->where('created_at', '<', $endExclusive)
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();
        $attributes = $row?->getAttributes() ?? [];
        $metrics = [];

        foreach ($buckets as $index => $_bucket) {
            $metrics[$index] = [
                'total' => (int) ($attributes["total_{$index}"] ?? 0),
                'active' => (int) ($attributes["active_{$index}"] ?? 0),
                'registered' => (int) ($attributes["registered_{$index}"] ?? 0),
                'quota' => (int) ($attributes["quota_{$index}"] ?? 0),
            ];
        }

        return $metrics;
    }

    /**
     *
     * Daily aggregate is preferred; source fallback is range-aware so an old row outside the requested six-week window cannot hide recent source data.
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable, label: string}>  $buckets
     * @return array<int, int>
     *
     */
    private function weeklyRegistrationCounts(
        CarbonImmutable $firstWeekStart,
        CarbonImmutable $endExclusive,
        array $buckets,
    ): array {
        $startDate = $firstWeekStart->toDateString();
        $endDate = $endExclusive->subDay()->toDateString();
        $aggregateRows = DB::table('event_daily_registrations')
            ->whereBetween('registration_date', [$startDate, $endDate])
            ->get(['registration_date', 'count']);

        if ($aggregateRows->isNotEmpty()) {
            $counts = array_fill(0, count($buckets), 0);

            foreach ($aggregateRows as $row) {
                $date = CarbonImmutable::parse((string) $row->registration_date, $firstWeekStart->getTimezone());
                $index = $firstWeekStart->diffInWeeks($date);

                if ($index >= 0 && $index < count($buckets)) {
                    $counts[$index] += (int) $row->count;
                }
            }

            return $counts;
        }

        $selects = [];
        $bindings = [];

        foreach ($buckets as $index => $bucket) {
            $selects[] = "SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS week_{$index}";
            $bindings[] = $bucket['start'];
            $bindings[] = $bucket['end'];
        }

        $row = DB::table('form_answers')
            ->where('created_at', '>=', $firstWeekStart)
            ->where('created_at', '<', $endExclusive)
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();
        $attributes = (array) ($row ?? []);

        return array_map(
            static fn (int $index): int => (int) ($attributes["week_{$index}"] ?? 0),
            array_keys($buckets),
        );
    }

    /**
     * Isi cache `/dashboard` di luar request path: alat opsional setelah deploy/seed agar
     * pengunjung pertama tidak menunggu. Kunci yang masih terisi dilewati kecuali `$force`.
     *
     * @return list<array{key: string, milliseconds: int, rows: int, skipped: bool}>
     */
    public function warmDashboardCache(bool $force = false): array
    {
        $steps = [
            [
                'key' => self::DASHBOARD_EVENT_STATS_KEY,
                'ttl' => self::DASHBOARD_EVENT_STATS_TTL_SECONDS,
                'resolver' => fn (): array => $this->computeDashboardEventStats(),
                'buster' => EventCache::BUSTER_STATS,
            ],
            [
                'key' => self::DASHBOARD_REGISTRANTS_KEY,
                'ttl' => self::DASHBOARD_REGISTRANTS_TTL_SECONDS,
                'resolver' => fn (): array => $this->computeDashboardRegistrants(),
                'buster' => EventCache::BUSTER_REGISTRANTS,
            ],
        ];

        return array_map(fn (array $step): array => $this->warmStep($step, $force), $steps);
    }

    /**
     * Jalankan satu langkah warm (hitung ulang + timpa cache) dan ukur durasinya;
     * nilai `skipped` menandakan kunci sudah terisi sehingga tidak dihitung ulang.
     *
     * @param  array{key: string, ttl: int, resolver: \Closure(): array<mixed>, buster: string}  $step
     * @return array{key: string, milliseconds: int, rows: int, skipped: bool}
     */
    private function warmStep(array $step, bool $force): array
    {
        if (! $force) {
            $existing = EventCache::peek($step['key'], $step['buster']);

            if ($existing !== null) {
                return [
                    'key' => $step['key'],
                    'milliseconds' => 0,
                    'rows' => count($existing),
                    'skipped' => true,
                ];
            }
        }

        $startedAt = hrtime(true);

        $rows = EventCache::refresh($step['key'], $step['ttl'], $step['resolver'], $step['buster']);

        return [
            'key' => $step['key'],
            'milliseconds' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'rows' => count($rows),
            'skipped' => false,
        ];
    }

    /**
     * Tiga agregat statistik event dashboard (mahal: tabel events 1 juta baris).
     *
     * @return array{totalEvents: int, activeEvents: int, completionRate: int}
     */
    private function computeDashboardEventStats(): array
    {
        $base = Event::query()->whereNull('deleted_at');

        $total = (clone $base)->count();

        // registrationStatus() berstatus Closed tepat saat registration_end sudah lewat.
        // Index dipin: tanpa hint, statistik tabel yang basi membuat MySQL memilih index lain (5,7 s vs 0,14 s).
        $closed = (clone $base)
            ->useIndex('events_deleted_registration_end_index')
            ->where('registration_end', '<', now())
            ->count();

        return [
            'totalEvents' => $total,
            'activeEvents' => (clone $base)->where('status', EventStatus::Published)->count(),
            'completionRate' => $total > 0 ? (int) round(($closed / $total) * 100) : 0,
        ];
    }

    /**
     * @return array{total: int}
     */
    private function computeDashboardRegistrants(): array
    {
        return ['total' => (int) Event::query()->whereNull('deleted_at')->sum('registered_count')];
    }
}

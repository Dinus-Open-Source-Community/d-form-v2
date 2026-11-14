<?php

namespace App\Services\Reporting;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\FormAnswer;
use App\Services\Event\EventCache;
use App\Services\Event\EventCategoryCounter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

final class EventReportingQuery
{
    public function __construct(private readonly EventCategoryCounter $categoryCounter)
    {
    }

    /** Ringkasan admin di-cache singkat; angka global jarang berubah di antara dua buka halaman. */
    private const GLOBAL_SUMMARY_TTL_SECONDS = 300;

    /*
     * Kunci & TTL agregat dashboard: satu sumber untuk request path (`HomeController`)
     * dan command `dashboard:warm`. TTL panjang aman karena perubahan event menaikkan
     * buster masing-masing; request dilarang menghitung ulang saat cache kosong.
     */
    public const DASHBOARD_EVENT_STATS_KEY = 'dashboard:stats:events';

    public const DASHBOARD_REGISTRANTS_KEY = 'dashboard:stats:registrants';

    private const DASHBOARD_WEEKLY_KPI_KEY = 'dashboard:stats:weekly-kpi';

    public const DASHBOARD_CATEGORY_TOKENS_KEY = 'report:category-tokens';

    private const DASHBOARD_EVENT_STATS_TTL_SECONDS = 900;

    private const DASHBOARD_REGISTRANTS_TTL_SECONDS = 60;

    private const DASHBOARD_WEEKLY_KPI_TTL_SECONDS = 300;

    private const DASHBOARD_CATEGORY_TOKENS_TTL_SECONDS = 21600;

    /** Cache fallback sumber: hanya dipakai sebelum agregat harian diisi. */
    public const REGISTRATION_TREND_SOURCE_KEY = 'reporting:registration-trend-source';

    public const BUSTER_REGISTRATION_TREND_SOURCE = 'reporting:registration-trend-source:buster';

    private const REGISTRATION_TREND_SOURCE_TTL_SECONDS = 21600;

    /** Jumlah baris agregat per statement INSERT saat rebuild (tabel kecil, aman untuk MariaDB). */
    private const REBUILD_INSERT_CHUNK_SIZE = 500;

    /**
     * Registration submissions per calendar month over the last N months.
     * Dibaca dari agregat harian `event_daily_registrations` (SUM(count)); sumber
     * 1 juta baris hanya dipakai bila tabel agregat belum pernah diisi.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    public function registrationTrendOverMonths(int $months = 6): array
    {
        $months = max(1, min(36, $months));

        $aggregateCounts = $this->registrationCountsFromAggregate($months);
        $counts = $aggregateCounts->isNotEmpty()
            ? $aggregateCounts
            : $this->registrationCountsFromSource($months);

        $out = [];
        $cursor = now()->startOfMonth()->subMonths($months - 1);
        $endMonth = now()->startOfMonth();

        while ($cursor->lte($endMonth)) {
            $key = $cursor->format('Y-m');
            $out[] = [
                'key' => $key,
                'label' => $cursor->translatedFormat('M Y'),
                'count' => (int) ($counts[$key] ?? 0),
            ];
            $cursor->addMonth();
        }

        return $out;
    }

    /**
     * Jumlah registrasi per bulan dari tabel agregat (satu baris per tanggal).
     *
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function registrationCountsFromAggregate(int $months)
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $end = $start->addMonths($months);

        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', registration_date)"
            : "DATE_FORMAT(registration_date, '%Y-%m')";

        return DB::table('event_daily_registrations')
            ->where('registration_date', '>=', $start->toDateString())
            ->where('registration_date', '<', $end->toDateString())
            ->selectRaw("{$monthExpr} as ym")
            ->selectRaw('SUM(count) as c')
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('c', 'ym');
    }

    /**
     * Fallback: hitung langsung dari form_answers (hanya bila agregat kosong).
     * Hasilnya di-cache per jumlah bulan agar cache dingin tidak memindai 1 juta
     * baris pada setiap request; `analytics:rebuild-daily-registrations` menaikkan
     * buster ini setelah agregat diisi.
     *
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function registrationCountsFromSource(int $months)
    {
        $cached = EventCache::remember(
            self::REGISTRATION_TREND_SOURCE_KEY.':'.$months,
            self::REGISTRATION_TREND_SOURCE_TTL_SECONDS,
            fn (): array => $this->computeRegistrationCountsFromSource($months)->all(),
            self::BUSTER_REGISTRATION_TREND_SOURCE,
        );

        return collect($cached);
    }

    /**
     * Scan sumber dibatasi index `form_answers_created_at_index`. Join `forms`/`events`
     * sengaja dihindari: optimizer memilih full scan `forms` (960k) + 1 juta lookup UUID
     * (>300 s di data stress). Definisi hitungannya sama dengan FormAnswerObserver
     * (semua submisi), jadi agregat hasil rebuild identik dengan fallback.
     *
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function computeRegistrationCountsFromSource(int $months)
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', form_answers.created_at)"
            : "DATE_FORMAT(form_answers.created_at, '%Y-%m')";

        return DB::table('form_answers')
            ->where('form_answers.created_at', '>=', $start)
            ->selectRaw("{$monthExpr} as ym")
            ->selectRaw('COUNT(*) as c')
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('c', 'ym');
    }

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

        EventCache::bump(self::BUSTER_REGISTRATION_TREND_SOURCE);

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
     * Count non-deleted events that include each category token at least once.
     * Sumbernya tabel agregat `event_category_counts`, jadi hitung ulang saat cache
     * kosong hanya menyentuh <=100 baris (bukan `GROUP BY` 1 juta baris `events`).
     *
     * @return list<array{token: string, count: int}>
     */
    public function eventsCountByCategoryToken(): array
    {
        // Agregasi di SQL: cursor() membuffer seluruh result set PDO dan OOM pada data besar.
        return EventCache::remember(
            self::DASHBOARD_CATEGORY_TOKENS_KEY,
            self::DASHBOARD_CATEGORY_TOKENS_TTL_SECONDS,
            fn (): array => $this->computeCategoryTokens(),
            EventCache::BUSTER_CATEGORY,
        );
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
     * Tren registrasi tidak ikut: sumbernya agregat harian, hanya 4 ms.
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
            [
                'key' => self::DASHBOARD_CATEGORY_TOKENS_KEY,
                'ttl' => self::DASHBOARD_CATEGORY_TOKENS_TTL_SECONDS,
                'resolver' => fn (): array => $this->computeCategoryTokens(),
                'buster' => EventCache::BUSTER_CATEGORY,
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

    /**
     * Token kategori dari tabel agregat (dijaga EventObserver + command rebuild),
     * sehingga tidak ada `GROUP BY` pada 1 juta baris walau cache sedang dingin.
     *
     * @return list<array{token: string, count: int}>
     */
    private function computeCategoryTokens(): array
    {
        $tokens = $this->categoryCounter->counts();

        // Urutan token dipertahankan A→Z seperti agregasi lama, walau tabel urut total desc.
        usort($tokens, static fn (array $a, array $b): int => strcmp($a['token'], $b['token']));

        return $tokens;
    }

    /**
     * @return array{
     *     submission_count: int,
     *     attended_count: int,
     *     attendance_rate_percent: float|null,
     *     registered_count: int,
     *     quota: int|null
     * }
     */
    public function eventReportingSummary(Event $event): array
    {
        $submissionCount = (int) FormAnswer::query()
            ->join('forms', 'form_answers.form_id', '=', 'forms.id')
            ->where('forms.event_id', $event->id)
            ->whereNull('forms.deleted_at')
            ->count();

        $attendedCount = (int) EventAttendance::query()
            ->where('event_id', $event->id)
            ->count();

        return [
            'submission_count' => $submissionCount,
            'attended_count' => $attendedCount,
            'attendance_rate_percent' => $submissionCount > 0
                ? round(($attendedCount / $submissionCount) * 100, 1)
                : null,
            'registered_count' => (int) $event->registered_count,
            'quota' => $event->quota !== null ? (int) $event->quota : null,
        ];
    }

    /**
     * Ringkasan agregat lintas event untuk halaman laporan; hasilnya di-cache 5 menit
     * dengan buster statistik agar tiga COUNT besar tidak dijalankan tiap buka halaman.
     *
     * @return array{
     *     total_events: int,
     *     total_submissions: int,
     *     total_attendance_records: int
     * }
     */
    public function globalAdminSummary(): array
    {
        return EventCache::remember(
            'reporting:global-admin-summary',
            self::GLOBAL_SUMMARY_TTL_SECONDS,
            $this->countGlobalAdminSummary(...),
            EventCache::BUSTER_STATS,
        );
    }

    /**
     * Hitung agregat global dari tabel sumber; hanya dipanggil saat cache ringkasan kosong.
     *
     * @return array{
     *     total_events: int,
     *     total_submissions: int,
     *     total_attendance_records: int
     * }
     */
    private function countGlobalAdminSummary(): array
    {
        $totalEvents = (int) Event::query()->whereNull('deleted_at')->count();

        $totalSubmissions = (int) FormAnswer::query()
            ->join('forms', 'form_answers.form_id', '=', 'forms.id')
            ->join('events', 'forms.event_id', '=', 'events.id')
            ->whereNull('events.deleted_at')
            ->whereNull('forms.deleted_at')
            ->count();

        $totalAttendance = (int) EventAttendance::query()
            ->join('events', 'event_attendances.event_id', '=', 'events.id')
            ->whereNull('events.deleted_at')
            ->count();

        return [
            'total_events' => $totalEvents,
            'total_submissions' => $totalSubmissions,
            'total_attendance_records' => $totalAttendance,
        ];
    }

    /**
     * Log kehadiran event, urut `scanned_at DESC, id DESC`.
     *
     * `scanned_at` hanya presisi detik: tie-breaker `id` (uuid) membuat urutan
     * deterministik, sehingga dua halaman tidak tumpang tindih atau melewatkan
     * baris saat banyak baris berbagi `scanned_at` yang sama.
     *
     * Halaman di luar `lastPage()` (`?page=999999`) di-clamp **sebelum** query
     * daftar dijalankan: total dihitung sekali, `lastPage` diturunkan dari total,
     * lalu satu query `LIMIT`/`OFFSET` memakai halaman hasil clamp. Tanpa ini,
     * nilai `page` raksasa memaksa MySQL berjalan sejauh OFFSET itu (dua kali)
     * pada setiap request.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateAttendanceLog(Event $event, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);

        $total = $this->attendanceLogQuery($event)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min(Paginator::resolveCurrentPage(), $lastPage));

        // `$total` diteruskan agar paginate() tidak menjalankan COUNT kedua.
        return $this->attendanceLogQuery($event)
            ->paginate($perPage, ['*'], 'page', $page, $total)
            ->through(function (EventAttendance $row): array {
                $user = $row->formAnswer?->user;
                $scanner = $row->scannedBy;

                return [
                    'id' => $row->id,
                    'scanned_at' => $row->scanned_at->toIso8601String(),
                    'form_answer_id' => $row->form_answer_id,
                    'attendee' => $user !== null
                        ? ['name' => $user->name, 'email' => $user->email]
                        : null,
                    'scanned_by' => $scanner !== null
                        ? ['name' => $scanner->name, 'email' => $scanner->email]
                        : null,
                ];
            });
    }

    /**
     * Query dasar log kehadiran: filter, eager load, dan urutan di satu tempat
     * agar COUNT dan SELECT tidak bisa drift.
     *
     * @return Builder<EventAttendance>
     */
    private function attendanceLogQuery(Event $event): Builder
    {
        return EventAttendance::query()
            ->where('event_id', $event->id)
            ->with([
                'formAnswer.user:id,name,email',
                'scannedBy:id,name,email',
            ])
            ->orderByDesc('scanned_at')
            ->orderByDesc('id');
    }
}

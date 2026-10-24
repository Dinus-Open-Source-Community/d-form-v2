<?php

namespace App\Services\Reporting;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\FormAnswer;
use App\Services\Event\EventCache;
use App\Services\Event\EventCategoryCounter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public const DASHBOARD_CATEGORY_TOKENS_KEY = 'report:category-tokens';

    private const DASHBOARD_EVENT_STATS_TTL_SECONDS = 900;

    private const DASHBOARD_REGISTRANTS_TTL_SECONDS = 60;

    private const DASHBOARD_CATEGORY_TOKENS_TTL_SECONDS = 21600;

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

        $counts = DB::table('event_daily_registrations')->exists()
            ? $this->registrationCountsFromAggregate($months)
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

        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', registration_date)"
            : "DATE_FORMAT(registration_date, '%Y-%m')";

        return DB::table('event_daily_registrations')
            ->where('registration_date', '>=', $start->toDateString())
            ->selectRaw("{$monthExpr} as ym")
            ->selectRaw('SUM(count) as c')
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('c', 'ym');
    }

    /**
     * Fallback: hitung langsung dari form_answers (hanya bila agregat kosong).
     *
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function registrationCountsFromSource(int $months)
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $driver = DB::connection()->getDriverName();
        $monthExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m', form_answers.created_at)",
            default => "DATE_FORMAT(form_answers.created_at, '%Y-%m')",
        };

        return FormAnswer::query()
            ->join('forms', 'form_answers.form_id', '=', 'forms.id')
            ->join('events', 'forms.event_id', '=', 'events.id')
            ->whereNull('events.deleted_at')
            ->whereNull('forms.deleted_at')
            ->where('form_answers.created_at', '>=', $start)
            ->selectRaw("{$monthExpr} as ym")
            ->selectRaw('COUNT(*) as c')
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('c', 'ym');
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
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateAttendanceLog(Event $event, int $perPage = 20): LengthAwarePaginator
    {
        return EventAttendance::query()
            ->where('event_id', $event->id)
            ->with([
                'formAnswer.user:id,name,email',
                'scannedBy:id,name,email',
            ])
            ->orderByDesc('scanned_at')
            ->paginate($perPage)
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
}

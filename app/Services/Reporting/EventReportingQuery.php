<?php

namespace App\Services\Reporting;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\FormAnswer;
use App\Services\Event\EventCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EventReportingQuery
{
    /** Ringkasan admin di-cache singkat; angka global jarang berubah di antara dua buka halaman. */
    private const GLOBAL_SUMMARY_TTL_SECONDS = 300;

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
     *
     * @return list<array{token: string, count: int}>
     */
    public function eventsCountByCategoryToken(): array
    {
        // Agregasi di SQL: cursor() membuffer seluruh result set PDO dan OOM pada data besar.
        return EventCache::remember(
            'report:category-tokens',
            3600,
            fn (): array => $this->computeCategoryTokens(),
            EventCache::BUSTER_CATEGORY,
        );
    }

    /**
     * Hitung token kategori aktual; dipisah agar bisa di-cache utuh.
     *
     * @return list<array{token: string, count: int}>
     */
    private function computeCategoryTokens(): array
    {
        $tallies = [];

        $countsByCategory = Event::query()
            ->whereNull('deleted_at')
            ->toBase()
            ->selectRaw('category, COUNT(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');

        foreach ($countsByCategory as $category => $count) {
            foreach (Event::tokensFromCsv((string) $category) as $token) {
                $tallies[$token] = ($tallies[$token] ?? 0) + (int) $count;
            }
        }

        uksort($tallies, static fn (string $a, string $b): int => strcmp($a, $b));

        return collect($tallies)->map(static fn (int $count, string $token): array => [
            'token' => $token,
            'count' => $count,
        ])->values()->all();
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

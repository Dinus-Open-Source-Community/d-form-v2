<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Event\EventCache;
use App\Services\Event\EventService;
use App\Services\Reporting\EventReportingQuery;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

/**
 * Beranda penyelenggara — hanya diakses lewat rute dengan middleware organizer.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventReportingQuery $reportingQuery,
    ) {
    }

    public function __invoke(Request $request): InertiaResponse
    {
        // Statistik event (mahal: 3 agregat 1 juta baris) hanya berubah saat event berubah.
        $eventStats = EventCache::remember(
            'dashboard:stats:events',
            300,
            fn (): array => $this->computeEventStats(),
            EventCache::BUSTER_STATS,
        );

        // Jumlah pendaftar berubah setiap registrasi; cache dipisah agar tidak ikut mahal.
        $registrants = EventCache::remember(
            'dashboard:stats:registrants',
            60,
            fn (): array => ['total' => (int) Event::query()->whereNull('deleted_at')->sum('registered_count')],
            EventCache::BUSTER_REGISTRANTS,
        );

        $stats = [
            ...$eventStats,
            'totalRegistrants' => $registrants['total'],
        ];

        // Kolom `description` tidak dipakai kartu dashboard; hanya 5 baris, tapi tetap dipangkas.
        $recentEvents = Event::query()
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get([
                'id', 'slug', 'title', 'location', 'banner', 'price', 'quota',
                'registered_count', 'category', 'session', 'status',
                'start_date', 'end_date', 'registration_start', 'registration_end',
                'created_at', 'updated_at',
            ])
            ->map(fn (Event $e): array => $this->eventService->eventToInertiaArray($e))
            ->values()
            ->all();

        $adminCharts = [
            'registrationTrend' => $this->reportingQuery->registrationTrendOverMonths(6),
            'categoryBreakdown' => $this->reportingQuery->eventsCountByCategoryToken(),
        ];

        return inertia('Dashboard/Index', [
            'recentEvents' => $recentEvents,
            'stats' => $stats,
            'adminCharts' => $adminCharts,
        ]);
    }

    /**
     * Empat agregat dashboard; dihitung sekali per TTL, bukan tiap request.
     *
     * @return array{totalEvents: int, activeEvents: int, totalRegistrants: int, completionRate: int}
     */
    private function computeEventStats(): array
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
}

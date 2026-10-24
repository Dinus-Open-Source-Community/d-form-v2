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
        $stats = EventCache::remember(
            'dashboard:stats',
            300,
            fn (): array => $this->computeStats(),
            EventCache::BUSTER_STATS,
        );

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
    private function computeStats(): array
    {
        $base = Event::query()->whereNull('deleted_at');

        $total = (clone $base)->count();

        // registrationStatus() berstatus Closed tepat saat registration_end sudah lewat.
        $closed = (clone $base)->where('registration_end', '<', now())->count();

        return [
            'totalEvents' => $total,
            'activeEvents' => (clone $base)->where('status', EventStatus::Published)->count(),
            'totalRegistrants' => (int) (clone $base)->sum('registered_count'),
            'completionRate' => $total > 0 ? (int) round(($closed / $total) * 100) : 0,
        ];
    }
}

<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Event\EventService;
use App\Services\Reporting\EventReportingQuery;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

/** Dashboard aggregates come from prewarmed cache; misses show zeroes until `dashboard:warm` repopulates it. */
class HomeController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventReportingQuery $reportingQuery,
    ) {
    }

    public function __invoke(Request $request): InertiaResponse
    {
        $eventStats = $this->reportingQuery->dashboardEventStats();

        $stats = [
            ...$eventStats,
            'totalRegistrants' => $this->reportingQuery->dashboardRegistrantTotal(),
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
}

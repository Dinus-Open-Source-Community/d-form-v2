<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
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
        $allEvents = Event::query()->whereNull('deleted_at')->get();

        $recentEvents = $allEvents
            ->sortByDesc('created_at')
            ->take(5)
            ->map(fn (Event $e) => $this->eventService->eventToInertiaArray($e))
            ->values()
            ->all();

        $total = $allEvents->count();
        $closed = $allEvents->filter(
            fn (Event $e) => $this->eventService->registrationStatus($e) === EventRegistrationStatus::Closed
        )->count();

        $stats = [
            'totalEvents' => $total,
            'activeEvents' => $allEvents->where('status', EventStatus::Published)->count(),
            'totalRegistrants' => (int) $allEvents->sum('registered_count'),
            'completionRate' => $total > 0 ? (int) round(($closed / $total) * 100) : 0,
        ];

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

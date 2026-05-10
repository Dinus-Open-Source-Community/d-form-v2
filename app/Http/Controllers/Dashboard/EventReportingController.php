<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Reporting\EventReportingQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventReportingController extends Controller
{
    public function __construct(
        private readonly EventReportingQuery $reportingQuery,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', Event::class);

        /** @var array{event?: string|null} $validated */
        $validated = $request->validate([
            'event' => ['sometimes', 'nullable', 'uuid', 'exists:events,id'],
        ]);

        $global = $this->reportingQuery->globalAdminSummary();

        $events = Event::query()
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get(['id', 'title', 'slug']);

        $eventsMinimal = $events
            ->map(static fn (Event $e): array => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
            ])
            ->values()
            ->all();

        $selectedId = isset($validated['event']) ? (string) $validated['event'] : null;

        /** @var Event|null $selected */
        $selected = null;
        if ($selectedId !== null) {
            $selected = Event::query()
                ->whereNull('deleted_at')
                ->whereKey($selectedId)
                ->first();
        }

        $eventPayload = null;
        $eventSummaryPayload = null;
        $exportUrls = null;
        $attendanceLog = null;

        if ($selected !== null) {
            $eventPayload = ['id' => $selected->id, 'title' => $selected->title, 'slug' => $selected->slug ?? ''];
            $eventSummaryPayload = $this->reportingQuery->eventReportingSummary($selected);
            $exportUrls = [
                'registrations' => route('dashboard.events.exports.registrations-csv', $selected),
                'attendance' => route('dashboard.events.exports.attendance-csv', $selected),
            ];
            $attendanceLog = $this->reportingQuery
                ->paginateAttendanceLog($selected, perPage: 15)
                ->appends(['event' => $selected->id]);
        }

        return Inertia::render('Dashboard/Reports/Index', [
            'globalSummary' => $global,
            'events' => $eventsMinimal,
            'selectedEventId' => $selected?->id,
            'selectedEvent' => $eventPayload,
            'selectedEventSummary' => $eventSummaryPayload,
            'exportUrls' => $exportUrls,
            'attendanceLog' => $attendanceLog,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Dashboard\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ShowEventReportRequest;
use App\Models\Event;
use App\Services\Event\EventService;
use App\Services\Reporting\EventReportingQuery;
use Inertia\Inertia;
use Inertia\Response;

class EventLaporanController extends Controller
{
    public function __construct(
        private readonly EventReportingQuery $eventReportingQuery,
        private readonly EventService $eventService,
    ) {
    }

    public function __invoke(ShowEventReportRequest $request, Event $event): Response
    {
        $this->authorize('view', $event);

        $attendanceLog = $this->eventReportingQuery
            ->paginateAttendanceLog($event, $request->perPage())
            ->withQueryString();

        return Inertia::render('Dashboard/Events/Laporan', [
            'globalSummary' => $this->eventReportingQuery->globalAdminSummary(),
            'event' => $this->eventService->eventToInertiaArray($event),
            'exports' => [
                'registrations' => route('dashboard.events.exports.registrations-csv', $event),
                'attendance' => route('dashboard.events.exports.attendance-csv', $event),
            ],
            'eventReporting' => [
                'summary' => $this->eventReportingQuery->eventReportingSummary($event),
                'attendanceLog' => $attendanceLog,
            ],
        ]);
    }
}

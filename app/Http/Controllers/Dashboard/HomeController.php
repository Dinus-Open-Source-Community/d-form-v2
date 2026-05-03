<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Event\EventService;
use Illuminate\Http\Request;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $adminScope = $user !== null && $user->can('events.list');

        $allEvents = $adminScope
            ? Event::query()->whereNull('deleted_at')->get()
            : Event::query()
                ->where('status', EventStatus::Published)
                ->whereNull('deleted_at')
                ->orderByDesc('start_date')
                ->get();

        $recentSource = $adminScope
            ? $allEvents->sortByDesc('created_at')->take(5)
            : $allEvents->take(5);

        $recentEvents = $recentSource
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

        return inertia('Dashboard/Index', [
            'recentEvents' => $recentEvents,
            'stats' => $stats,
        ]);
    }
}

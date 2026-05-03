<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\Event\EventService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventsController extends Controller
{
    public function index(EventService $eventService)
    {
        $events = Event::query()
            ->where('status', EventStatus::Published)
            ->whereNull('deleted_at')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Event $e) => $eventService->eventToInertiaArray($e))
            ->values()
            ->all();

        return inertia('Event', [
            'events' => $events,
        ]);
    }

    public function show(string $id, EventService $eventService)
    {
        $event = Event::query()
            ->where('status', EventStatus::Published)
            ->whereNull('deleted_at')
            ->whereKey($id)
            ->first();

        if ($event === null) {
            throw new NotFoundHttpException;
        }

        return inertia('EventDetail', [
            'event' => $eventService->eventToInertiaArray($event),
        ]);
    }
}

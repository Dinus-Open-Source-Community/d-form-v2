<?php

namespace App\Http\Controllers;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Services\Event\EventService;
use App\Services\Event\UserPortalEventResolver;

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

    public function show(string $segment, EventService $eventService, UserPortalEventResolver $resolver)
    {
        $event = $resolver->resolvePublished($segment);

        $segment = $event->slug !== null && $event->slug !== ''
            ? $event->slug
            : (string) $event->getKey();

        $forms = Form::query()
            ->where('event_id', $event->id)
            ->orderBy('title')
            ->get()
            ->filter(fn (Form $form): bool => $form->visible_for?->contains(EventFormVisibility::Public) ?? false)
            ->map(fn (Form $form): array => [
                'id' => $form->id,
                'title' => $form->title,
                'description' => $form->description,
                'fill_url' => route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false),
            ])
            ->values()
            ->all();

        return inertia('EventDetail', [
            'event' => $eventService->eventToInertiaArray($event),
            'memberPortalEventUrl' => route('dashboard.user.events.show', [
                'event_segment' => $segment,
            ], absolute: false),
            'forms' => $forms,
        ]);
    }
}

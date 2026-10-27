<?php

namespace App\Http\Controllers;

use App\Enums\EventFormVisibility;
use App\Http\Requests\PublicEventSearchRequest;
use App\Models\Event;
use App\Models\Form;
use App\Services\Event\EventService;
use App\Services\Event\PublicEventSearch;
use App\Services\Event\UserPortalEventResolver;

class EventsController extends Controller
{
    /**
     * Daftar acara publik: 10 per halaman, kata kunci `q` dicari di server.
     */
    public function index(PublicEventSearchRequest $request, EventService $eventService, PublicEventSearch $eventSearch)
    {
        $validated = $request->validated();

        $search = trim((string) ($validated['q'] ?? ''));

        // Halaman dalam pada hasil pencarian tidak berguna (dan mahal): kembali ke halaman 1.
        $maxPage = (int) config('events.search.max_page', PublicEventSearch::MAX_PAGE);

        if ($search !== '' && (int) ($validated['page'] ?? 1) > $maxPage) {
            return redirect()->route('events.index', ['q' => $search]);
        }

        $paginator = $eventSearch->page($search === '' ? null : $search);

        if ($paginator === null) {
            return inertia('Event', [
                'events' => [],
                'highlight' => $eventService->publicListHighlight(),
                'pagination' => ['links' => [], 'currentPage' => null, 'hasMore' => false],
                'filters' => ['q' => $search],
                'searchError' => 'Kata kunci terlalu umum, coba lebih spesifik.',
            ]);
        }

        return inertia('Event', [
            'events' => array_map(
                fn (Event $event): array => $eventService->eventToInertiaArray($event),
                $paginator->items(),
            ),
            'highlight' => $eventService->publicListHighlight(),
            'pagination' => [
                'links' => $eventService->paginationLinks($paginator),
                'currentPage' => $paginator instanceof \Illuminate\Pagination\Paginator
                    ? $paginator->currentPage()
                    : null,
                'hasMore' => $paginator->hasMorePages(),
            ],
            'filters' => ['q' => $search === '' ? null : $search],
            'searchError' => null,
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

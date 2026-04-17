<?php

namespace App\Http\Controllers\Dashboard\Events;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexEventRequest;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Form;
use Illuminate\Http\JsonResponse;
use App\Services\Event\EventService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService
    ) {}

    public function index(IndexEventRequest $request): Response
    {
        $validated = $request->validated();
        $page = $request->integer('page', 1);

        $paginator = $this->eventService->paginateForAdminIndex($validated, $page);

        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (Event $event) => $this->eventService->eventToInertiaArray($event)
            )
        );

        return Inertia::render('Dashboard/Events/Index', [
            'events' => $paginator,
            'filterOptions' => $this->filterOptions(),
            'query' => $validated,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Event::class);

        return Inertia::render('Dashboard/Events/Create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $banner = $request->file('banner');

        $event = $this->eventService->create($data, $banner);

        Inertia::flash('toast', [
            'message' => __('messages.event.create.success'),
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.events.show', $event);
    }

    public function show(Event $event): Response
    {
        $this->authorize('view', $event);

        $forms = Form::query()
            ->where('event_id', $event->id)
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Dashboard/Events/Show', [
            'event' => $this->eventService->eventToInertiaArray($event),
            'forms' => $forms,
        ]);
    }

    public function registrationStatus(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json(
            $this->eventService->eventToInertiaArray($event)
        );
    }

    public function edit(Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('Dashboard/Events/Edit', [
            'event' => (new EventResource($event))->resolve(request()),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $data = $request->validated();
        $banner = $request->file('banner');

        $this->eventService->update($event, $data, $banner);

        Inertia::flash('toast', [
            'message' => __('messages.event.edit.success'),
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.events.show', $event);
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $this->eventService->delete($event);

        Inertia::flash('toast', [
            'message' => __('messages.event.delete.success'),
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.events.index');
    }

    public function restore(Event $event): RedirectResponse
    {
        $this->authorize('restore', $event);

        $this->eventService->restore($event);

        Inertia::flash('toast', [
            'message' => __('messages.event.restore.success'),
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.events.show', $event);
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function formOptions(): array
    {
        return [
            'categories' => collect(EventCategory::cases())
                ->map(fn (EventCategory $c) => [
                    'value' => $c->value,
                    'label' => strip_tags((string) $c->getLabel()),
                ])
                ->values()
                ->all(),
            'sessions' => collect(EventSession::cases())
                ->map(fn (EventSession $s) => [
                    'value' => $s->value,
                    'label' => $s->getLabel(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function filterOptions(): array
    {
        return [
            ...$this->formOptions(),
            'statuses' => collect(EventStatus::cases())
                ->map(fn (EventStatus $s) => [
                    'value' => $s->value,
                    'label' => $s->getLabel(),
                ])
                ->values()
                ->all(),
        ];
    }
}

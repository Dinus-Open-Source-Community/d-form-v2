<?php

namespace App\Http\Controllers\Dashboard\Events;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexEventRequest;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Event\EventCategoryOptionService;
use App\Services\Event\EventService;
use App\Services\Event\EventSessionOptionService;
use App\Support\FormFieldTypeMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventCategoryOptionService $eventCategoryOptionService,
        private readonly EventSessionOptionService $eventSessionOptionService,
    ) {
    }

    public function index(IndexEventRequest $request): Response
    {
        $validated = $request->validated();
        $page = $request->integer('page', 1);

        $paginator = $this->eventService->paginateForAdminIndex($validated, $page, auth()->guard('web')->user());

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

        $draftId = $this->validDraftQueryId();

        if ($draftId !== null) {
            $event = Event::query()
                ->with(['forms.formFields' => fn ($q) => $q->orderBy('order')])
                ->findOrFail($draftId);

            $this->authorize('update', $event);

            return Inertia::render('Dashboard/Events/Create', [
                'options' => $this->formOptions(),
                'draftEvent' => $this->draftEventPayload($event),
            ]);
        }

        return Inertia::render('Dashboard/Events/Create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse|JsonResponse|Response
    {
        $data = $request->validated();
        $banner = $request->file('banner');

        $event = $this->eventService->create($data, $banner, auth()->guard('web')->user());

        if ($request->header('X-Inertia') || $request->wantsJson()) {
            // Klien wizard (Inertia/fetch JSON): pastikan draft punya satu form
            // default agar step forms (autosave fields) punya form id untuk POST fields.
            if ($event->forms()->count() === 0) {
                $event->forms()->create([
                    'title' => '',
                    'description' => '',
                    'success_content' => null,
                    'closed_at' => null,
                    'visible_for' => [EventFormVisibility::Public->value],
                    'banner_url' => null,
                    'banner_caption' => null,
                    'metadata' => null,
                ]);
            }

            // Non-Inertia caller (plain fetch/axios, Accept: application/json)
            // → JSON draft pointer.
            if (! $request->header('X-Inertia')) {
                return response()->json([
                    'event' => $event->only('id', 'slug'),
                ]);
            }

            // Inertia caller → same-page 200 so `onSuccess` reads page.props.draftEvent.
            $event->load(['forms.formFields' => fn ($q) => $q->orderBy('order')]);

            return Inertia::render('Dashboard/Events/Create', [
                'options' => $this->formOptions(),
                'draftEvent' => $this->draftEventPayload($event),
            ]);
        }

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
            'categories' => $this->eventCategoryOptionService->forForm(),
            'sessions' => $this->eventSessionOptionService->forForm(),
        ];
    }

    private function validDraftQueryId(): ?string
    {
        $raw = request()->query('draftId');

        return is_string($raw) && $raw !== '' ? $raw : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function draftEventPayload(Event $event): array
    {
        return array_merge(
            (new EventResource($event))->resolve(request()),
            [
                'forms' => $event->forms
                    ->map(fn (Form $form) => $this->draftFormPayload($form))
                    ->values()
                    ->all(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function draftFormPayload(Form $form): array
    {
        $meta = $form->metadata;
        if (! is_array($meta)) {
            $meta = $meta instanceof \Illuminate\Support\Collection ? $meta->all() : (array) $meta;
        }

        return [
            'id' => $form->id,
            'title' => $form->title,
            'description' => $form->description,
            'success_content' => $form->success_content,
            'closed_at' => $form->closed_at?->format('Y-m-d\TH:i'),
            'visible_for' => collect($form->visible_for ?? [])
                ->map(fn ($v) => $v instanceof EventFormVisibility ? $v->value : $v)
                ->values()
                ->all(),
            'event_id' => $form->event_id,
            'banner_url' => $form->banner_url,
            'banner_caption' => $form->banner_caption,
            'metadata' => $meta,
            'fields' => $form->formFields
                ->map(fn (FormField $f) => FormFieldTypeMapping::fieldToInertia($f))
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

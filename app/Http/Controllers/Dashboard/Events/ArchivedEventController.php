<?php

namespace App\Http\Controllers\Dashboard\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexArchivedEventRequest;
use App\Models\Event;
use App\Services\Event\EventCategoryOptionService;
use App\Services\Event\EventService;
use App\Services\Event\EventSessionOptionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Halaman daftar acara terarsip (soft-deleted) dan aksi hapus permanennya. */
class ArchivedEventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventCategoryOptionService $eventCategoryOptionService,
        private readonly EventSessionOptionService $eventSessionOptionService,
    ) {
    }

    public function index(IndexArchivedEventRequest $request): Response
    {
        $validated = $request->validated();
        // OFFSET dalam pada 1 juta baris = lambat; batasi halaman yang bisa diminta.
        $page = min(max(1, $request->integer('page', 1)), (int) config('events.admin.max_page', 200));

        $paginator = $this->eventService->paginateArchivedForAdminIndex($validated, $page, auth()->guard('web')->user());

        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (Event $event) => $this->eventService->eventToInertiaArray($event)
            )
        );

        return Inertia::render('Dashboard/Events/Archived', [
            'events' => $paginator,
            'filterOptions' => [
                'categories' => $this->eventCategoryOptionService->forForm(),
                'sessions' => $this->eventSessionOptionService->forForm(),
            ],
            'query' => $validated,
        ]);
    }

    /** Hapus permanen acara terarsip; policy menolak acara yang masih aktif. */
    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('forceDelete', $event);

        $this->eventService->forceDelete($event);

        Inertia::flash('toast', [
            'message' => __('messages.event.force_delete.success'),
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.events.archived');
    }
}

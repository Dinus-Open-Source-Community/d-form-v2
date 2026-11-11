<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Models\Event;
use App\Services\Event\EventService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response as InertiaResponse;

class UserBrowseEventsController
{
    /** Render the published event browser with its existing filter validation. */
    public function __invoke(Request $request, EventService $eventService): InertiaResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', Rule::in(array_column(EventCategory::cases(), 'value'))],
            'session' => ['nullable', 'string', Rule::in(array_column(EventSession::cases(), 'value'))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = [
            'category' => $validated['category'] ?? null,
            'session' => $validated['session'] ?? null,
        ];

        $paginator = $eventService->paginateForPublicBrowse($filters);

        return inertia('Dashboard/User/Events', [
            'events' => array_map(
                fn (Event $event): array => $eventService->eventToInertiaArray($event),
                $paginator->items(),
            ),
            'pagination' => [
                'links' => $eventService->paginationLinks($paginator),
                'currentPage' => null,
                'hasMore' => $paginator->hasMorePages(),
            ],
            'filters' => $filters,
            'listMode' => 'browse',
        ]);
    }
}

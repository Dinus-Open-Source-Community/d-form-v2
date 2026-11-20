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
    public function __invoke(Request $request, EventService $eventService): InertiaResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', Rule::in(array_column(EventCategory::cases(), 'value'))],
            'session' => ['nullable', 'string', Rule::in(array_column(EventSession::cases(), 'value'))],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filters = [
            'category' => $validated['category'] ?? null,
            'session' => $validated['session'] ?? null,
        ];

        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = (int) ($validated['per_page'] ?? EventService::PUBLIC_BROWSE_PER_PAGE);

        $paginator = $eventService->paginateForPublicBrowse($filters, $page, $perPage);

        return inertia('Dashboard/User/Events', [
            'events' => array_map(
                fn (Event $event): array => $eventService->eventToInertiaArray($event),
                $paginator->items(),
            ),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
            ],
            'filters' => $filters,
            'listMode' => 'browse',
        ]);
    }
}

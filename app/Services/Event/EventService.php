<?php

namespace App\Services\Event;

use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class EventService
{
    private const LIST_CACHE_TTL_SECONDS = 3600;

    public static function normalizePriceInput(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $string = (string) $value;

        return (float) str_replace(['.', ','], ['', '.'], $string);
    }

    public function registrationStatus(Event $event, ?int $registeredCount = null): EventRegistrationStatus
    {
        $registeredCount ??= $this->resolveRegisteredCount($event);
        $registrationStart = Carbon::parse($event->registration_start);
        $registrationEnd = Carbon::parse($event->registration_end);
        $now = Carbon::now();

        if ($now->lt($registrationStart)) {
            return EventRegistrationStatus::NotYetOpen;
        }

        if ($now->gt($registrationEnd)) {
            return EventRegistrationStatus::Closed;
        }

        if ($registeredCount >= (int) $event->quota) {
            return EventRegistrationStatus::Full;
        }

        return EventRegistrationStatus::Open;
    }

    public function resolveRegisteredCount(Event $event): int
    {
        return min(max(0, (int) $event->registered_count), (int) $event->quota);
    }

    /**
     * @param  array{search: string, filter: array{categories: array, sessions: array, statuses: array, showTrashed: bool}, sort: array{by: string, order: string}, per_page: int}  $queryInput
     */
    public function paginateForAdminIndex(array $queryInput, int $page): LengthAwarePaginator
    {
        $perPage = max(1, min(100, (int) ($queryInput['per_page'] ?? 10)));
        $search = $queryInput['search'] ?? '';
        $filter = $queryInput['filter'] ?? [];
        $sort = $queryInput['sort'] ?? ['by' => 'title', 'order' => 'asc'];

        $hashedQuery = md5(json_encode([
            'filter' => $filter,
            'sort' => $sort,
            'search' => $search,
            'pagination' => [
                'per_page' => $perPage,
                'page' => $page,
            ],
            'buster' => Cache::get('events:list:cache:buster', 0),
        ]));

        return $this->rememberList(
            "list-page:events:{$hashedQuery}",
            function () use ($filter, $sort, $search, $perPage, $page) {
                $query = Event::query();

                if (! empty($filter['showTrashed'])) {
                    $query->withTrashed();
                }

                if (count($filter['categories'] ?? []) > 0) {
                    $query->whereIn('category', $filter['categories']);
                }

                if (count($filter['sessions'] ?? []) > 0) {
                    $query->whereIn('session', $filter['sessions']);
                }

                if (count($filter['statuses'] ?? []) > 0) {
                    $query->whereIn('status', $filter['statuses']);
                }

                if ($search !== '') {
                    $query->whereLike('title', "%{$search}%");
                }

                $query->forListPage();

                $sortBy = $sort['by'] ?? 'title';
                $sortOrder = $sort['order'] ?? 'asc';

                return $query
                    ->orderBy($sortBy, $sortOrder)
                    ->paginate($perPage, ['*'], 'page', $page);
            }
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, UploadedFile $banner): Event
    {
        unset($data['banner']);

        $path = $banner->store('events/banners', 'public');

        $status = ! empty($data['publish']) ? EventStatus::Published : EventStatus::Draft;
        unset($data['publish']);

        $data['registered_count'] = 0;
        $data['banner'] = $path;
        $data['status'] = $status;

        return Event::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data, ?UploadedFile $banner = null): Event
    {
        unset($data['banner']);

        if (array_key_exists('publish', $data)) {
            $data['status'] = ! empty($data['publish']) ? EventStatus::Published : EventStatus::Draft;
        }
        unset($data['publish']);

        if ($banner !== null) {
            if ($event->banner) {
                Storage::disk('public')->delete($event->banner);
            }
            $data['banner'] = $banner->store('events/banners', 'public');
        }

        if (array_key_exists('quota', $data) && ! array_key_exists('registered_count', $data)) {
            $data['registered_count'] = min($this->resolveRegisteredCount($event), (int) $data['quota']);
        }

        $event->update($data);

        return $event->fresh();
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }

    public function restore(Event $event): void
    {
        $event->restore();
    }

    /**
     * @return array<string, mixed>
     */
    public function eventToInertiaArray(Event $event, ?int $registeredCount = null): array
    {
        $registeredCount ??= $this->resolveRegisteredCount($event);

        return array_merge(
            (new EventResource($event))->resolve(request()),
            [
                'registration_status' => $this->registrationStatus($event, $registeredCount)->value,
                'registered_count' => $registeredCount,
            ]
        );
    }

    private function rememberList(string $key, \Closure $callback): mixed
    {
        try {
            return Cache::tags(['events'])->remember($key, self::LIST_CACHE_TTL_SECONDS, $callback);
        } catch (\BadMethodCallException|\RuntimeException) {
            return Cache::remember($key, self::LIST_CACHE_TTL_SECONDS, $callback);
        }
    }
}

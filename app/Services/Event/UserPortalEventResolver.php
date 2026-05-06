<?php

namespace App\Services\Event;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Support\Str;

final class UserPortalEventResolver
{
    public function resolvePublished(string $segment): Event
    {
        if (Str::isUuid($segment)) {
            return Event::query()
                ->where('status', EventStatus::Published)
                ->whereKey($segment)
                ->firstOrFail();
        }

        return Event::query()
            ->where('status', EventStatus::Published)
            ->where('slug', $segment)
            ->firstOrFail();
    }
}

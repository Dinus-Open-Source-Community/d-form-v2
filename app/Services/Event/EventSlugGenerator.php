<?php

namespace App\Services\Event;

use App\Models\Event;
use Illuminate\Support\Str;

final class EventSlugGenerator
{
    /**
     * Produce a unique URL slug from an event title.
     */
    public function generateForTitle(string $title, ?string $excludeEventId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'event';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->slugTaken($candidate, $excludeEventId)) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    private function slugTaken(string $slug, ?string $excludeEventId): bool
    {
        $query = Event::query()->where('slug', $slug);

        if ($excludeEventId !== null) {
            $query->where('id', '!=', $excludeEventId);
        }

        return $query->exists();
    }
}

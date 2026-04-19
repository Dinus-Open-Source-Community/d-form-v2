<?php

namespace App\Http\Requests\Concerns;

use App\Enums\EventCategory;
use App\Support\EventCategoryTokens;

trait NormalizesEventCategoryRequest
{
    protected function mergeNormalizedCategoryFromRequest(): void
    {
        if (! $this->has('category')) {
            return;
        }

        $raw = $this->input('category');

        if (is_array($raw)) {
            $segments = array_map(static fn (mixed $s) => (string) $s, $raw);
        } elseif (is_string($raw)) {
            $segments = explode(',', $raw);
        } else {
            return;
        }

        $order = array_flip(array_map(
            static fn (EventCategory $c) => $c->value,
            EventCategory::cases()
        ));

        $enumParts = [];
        $customParts = [];

        foreach ($segments as $segment) {
            $t = trim((string) $segment);

            if ($t === '') {
                continue;
            }

            $enumVal = EventCategoryTokens::tryResolveEnumBackingValue($t);

            if ($enumVal !== null) {
                $enumParts[$enumVal] = $enumVal;

                continue;
            }

            if (EventCategoryTokens::isValidCustomToken($t)) {
                $v = EventCategoryTokens::normalizeCustomValue($t);
                $customParts[$v] = $v;
            }
        }

        $enumOrdered = collect($enumParts)
            ->keys()
            ->sortBy(static fn (string $v) => $order[$v] ?? PHP_INT_MAX)
            ->values()
            ->all();

        $customOrdered = collect($customParts)
            ->keys()
            ->sort()
            ->values()
            ->all();

        $this->merge(['category' => implode(',', array_merge($enumOrdered, $customOrdered))]);
    }
}

<?php

namespace App\Http\Requests\Concerns;

use App\Enums\EventSession;
use App\Support\EventSessionTokens;

trait NormalizesEventSessionRequest
{
    protected function mergeNormalizedSessionFromRequest(): void
    {
        if (! $this->has('session')) {
            return;
        }

        $raw = $this->input('session');

        if (is_array($raw)) {
            $segments = array_map(static fn (mixed $s) => (string) $s, $raw);
        } elseif (is_string($raw)) {
            $segments = explode(',', $raw);
        } else {
            return;
        }

        $order = array_flip(array_map(
            static fn (EventSession $s) => $s->value,
            EventSession::cases()
        ));

        $enumParts = [];
        $customParts = [];

        foreach ($segments as $segment) {
            $t = trim((string) $segment);

            if ($t === '') {
                continue;
            }

            $enumVal = EventSessionTokens::tryResolveEnumBackingValue($t);

            if ($enumVal !== null) {
                $enumParts[$enumVal] = $enumVal;

                continue;
            }

            if (EventSessionTokens::isValidCustomToken($t)) {
                $v = EventSessionTokens::normalizeCustomValue($t);
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

        $this->merge(['session' => implode(',', array_merge($enumOrdered, $customOrdered))]);
    }
}

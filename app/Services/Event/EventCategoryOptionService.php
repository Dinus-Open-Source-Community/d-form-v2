<?php

namespace App\Services\Event;

use App\Enums\EventCategory;
use App\Models\Event;
use App\Support\EventCategoryTokens;
use Illuminate\Support\Str;

class EventCategoryOptionService
{
    /**
     * Category options for dashboard forms, built from distinct values in `events.category`
     * (comma-separated tokens are split and deduplicated). Includes all enum options plus any
     * valid custom tokens found in the database (e.g. legacy values like "must").
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function forForm(): array
    {
        $rows = Event::query()
            ->toBase()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->pluck('category');

        return $this->optionsForRawCategoryRows($rows);
    }

    /**
     * Same resolution as {@see forForm()} but from raw strings (for tests and reuse).
     *
     * @param  iterable<int|string, mixed>  $rawCategoryRows
     * @return array<int, array{value: string, label: string}>
     */
    public function optionsForRawCategoryRows(iterable $rawCategoryRows): array
    {
        $customValueToLabel = [];

        $hasAnyRow = false;

        foreach ($rawCategoryRows as $raw) {
            if (! is_string($raw)) {
                continue;
            }

            $hasAnyRow = true;

            foreach (explode(',', $raw) as $segment) {
                $trimmed = trim($segment);

                if ($trimmed === '') {
                    continue;
                }

                $enumVal = EventCategoryTokens::tryResolveEnumBackingValue($trimmed);

                if ($enumVal !== null) {
                    continue;
                }

                if (! EventCategoryTokens::isValidCustomToken($trimmed)) {
                    continue;
                }

                $v = EventCategoryTokens::normalizeCustomValue($trimmed);
                $customValueToLabel[$v] ??= Str::title(Str::lower($trimmed));
            }
        }

        if (! $hasAnyRow) {
            return $this->allEnumOptions();
        }

        $options = [];

        foreach (EventCategory::cases() as $case) {
            $options[] = [
                'value' => $case->value,
                'label' => strip_tags((string) $case->getLabel()),
            ];
        }

        $enumValues = array_map(static fn (EventCategory $c) => $c->value, EventCategory::cases());
        $customKeys = array_keys($customValueToLabel);
        sort($customKeys);

        foreach ($customKeys as $value) {
            if (in_array($value, $enumValues, true)) {
                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => $customValueToLabel[$value],
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function allEnumOptions(): array
    {
        return collect(EventCategory::cases())
            ->map(static fn (EventCategory $c) => [
                'value' => $c->value,
                'label' => strip_tags((string) $c->getLabel()),
            ])
            ->values()
            ->all();
    }
}

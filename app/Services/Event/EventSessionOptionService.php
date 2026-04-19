<?php

namespace App\Services\Event;

use App\Enums\EventSession;
use App\Models\Event;
use App\Support\EventSessionTokens;
use Illuminate\Support\Str;

class EventSessionOptionService
{
    /**
     * Session options for dashboard forms, built from distinct values in `events.session`.
     * Includes all enum options plus any valid custom tokens found in the database.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function forForm(): array
    {
        $rows = Event::query()
            ->toBase()
            ->whereNotNull('session')
            ->where('session', '!=', '')
            ->pluck('session');

        return $this->optionsForRawSessionRows($rows);
    }

    /**
     * Same resolution as {@see forForm()} but from raw strings (for tests and reuse).
     *
     * @param  iterable<int|string, mixed>  $rawSessionRows
     * @return array<int, array{value: string, label: string}>
     */
    public function optionsForRawSessionRows(iterable $rawSessionRows): array
    {
        $customValueToLabel = [];

        $hasAnyRow = false;

        foreach ($rawSessionRows as $raw) {
            if (! is_string($raw)) {
                continue;
            }

            $hasAnyRow = true;

            foreach (explode(',', $raw) as $segment) {
                $trimmed = trim($segment);

                if ($trimmed === '') {
                    continue;
                }

                $enumVal = EventSessionTokens::tryResolveEnumBackingValue($trimmed);

                if ($enumVal !== null) {
                    continue;
                }

                if (! EventSessionTokens::isValidCustomToken($trimmed)) {
                    continue;
                }

                $v = EventSessionTokens::normalizeCustomValue($trimmed);
                $customValueToLabel[$v] ??= Str::title(Str::lower($trimmed));
            }
        }

        if (! $hasAnyRow) {
            return $this->allEnumOptions();
        }

        $options = [];

        foreach (EventSession::cases() as $case) {
            $options[] = [
                'value' => $case->value,
                'label' => strip_tags((string) $case->getLabel()),
            ];
        }

        $enumValues = array_map(static fn (EventSession $c) => $c->value, EventSession::cases());
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
        return collect(EventSession::cases())
            ->map(static fn (EventSession $c) => [
                'value' => $c->value,
                'label' => strip_tags((string) $c->getLabel()),
            ])
            ->values()
            ->all();
    }
}

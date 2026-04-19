<?php

namespace App\Rules;

use App\Support\EventSessionTokens;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class CommaSeparatedEventSessions implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail(__('validation.required', ['attribute' => $attribute]));

            return;
        }

        foreach (array_unique(array_filter(array_map('trim', explode(',', $value)))) as $part) {
            if (EventSessionTokens::tryResolveEnumBackingValue($part) !== null) {
                continue;
            }

            if (EventSessionTokens::isValidCustomToken($part)) {
                continue;
            }

            $fail(__('validation.enum', ['attribute' => $attribute]));

            return;
        }
    }
}

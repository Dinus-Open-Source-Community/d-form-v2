<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Menegakkan satu pola input ketat dari {@see \App\Support\FieldInputPatterns} beserta pesan Indonesianya. */
final class FieldInputPattern implements ValidationRule
{
    /**
     * @param  string  $pattern  Regex ber-anchor yang harus cocok dengan nilai setelah normalisasi.
     * @param  string  $message  Pesan gagal berbahasa Indonesia untuk subtype ini.
     * @param  (Closure(string): string)|null  $normalize  Normalisasi opsional sebelum pencocokan pola.
     */
    public function __construct(
        private readonly string $pattern,
        private readonly string $message,
        private readonly ?Closure $normalize = null,
    ) {
    }

    /** Nilai kosong dilewati (aturan `required` yang menanganinya); nilai terisi wajib cocok dengan pola. */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return;
        }

        $candidate = (string) $value;

        if ($this->normalize !== null) {
            $candidate = ($this->normalize)($candidate);
        }

        if ($candidate === '') {
            return;
        }

        if (preg_match($this->pattern, $candidate) !== 1) {
            $fail($this->message);
        }
    }
}

<?php

namespace App\Http\Requests\Concerns;

use App\Services\Broadcasting\BroadcastHtmlSanitizer;

/**
 * Sanitasi HTML pada input request sebelum validasi dan penyimpanan.
 *
 * Dipakai bersama oleh event, form, dan field builder agar tidak ada
 * implementasi sanitasi kedua di controller.
 */
trait SanitizesRichHtmlInput
{
    /**
     * Ganti field di request dengan versi bersih dari allowlist sanitizer;
     * nilai tanpa tag HTML dibiarkan apa adanya.
     *
     * @param array<int, string> $fields
     */
    protected function mergeSanitizedHtml(array $fields): void
    {
        $sanitizer = app(BroadcastHtmlSanitizer::class);
        $merged = [];

        foreach ($fields as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $merged[$field] = $sanitizer->sanitizeIfMarkup($value);
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * Sanitasi `fields.*.description` dan `fields.*.metadata.content` (teks
     * bantu builder) bila mengandung tag HTML.
     */
    protected function mergeSanitizedFormFieldHtml(): void
    {
        $fields = $this->input('fields');

        if (! is_array($fields)) {
            return;
        }

        $sanitizer = app(BroadcastHtmlSanitizer::class);

        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                continue;
            }

            if (is_string($field['description'] ?? null)) {
                $fields[$index]['description'] = $sanitizer->sanitizeIfMarkup($field['description']);
            }

            if (is_string($field['metadata']['content'] ?? null)) {
                $fields[$index]['metadata']['content'] = $sanitizer->sanitizeIfMarkup($field['metadata']['content']);
            }
        }

        $this->merge(['fields' => $fields]);
    }
}

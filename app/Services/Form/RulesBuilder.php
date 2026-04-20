<?php

namespace App\Services\Form;

use Illuminate\Support\Collection;

class RulesBuilder
{
    public const DIRECT_MAP = [
        'min'      => 'min',
        'max'      => 'max',
        'mimes'    => 'mimes',
        'max_size' => 'max',             // Laravel pakai 'max' untuk size file (KB)
        'min_date' => 'after_or_equal',
        'max_date' => 'before_or_equal',
    ];

    /**
     * Method ini untuk mengambil rules dari field-field untuk dibuild nantinya
     * @template T of \App\Models\FormField
     * @param Collection<int, T> $fields
     *
     */
    public static function extractRulesFromFields(Collection $fields): array
    {
        $result = $fields->mapWithKeys(function ($field) {
            $rules = $field->metadata['rules'];

            if ($field->type === 'input' && ($field->metadata['type'] ?? '') === 'email') {
                $rules['email'] = true;
            }

            return [
                $field->name => $rules ?? []
            ];
        })->all();

        return $result;
    }

    /**
    * Mengonversi custom rules array menjadi Laravel Validation rules.
    * * @param array<string, array<string, mixed>> $customRules
    * @return array<string, array<int, string>>
    */
    public static function build(array $customRules): array
    {
        $laravelRules = [];

        foreach ($customRules as $fieldName => $rules) {
            $mappedRules = [];

            // 1. Handling Required vs Nullable
            $isRequired = filter_var($rules['required'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($isRequired) {
                $mappedRules[] = 'required';
            } else {
                $mappedRules[] = 'nullable';
            }

            // 2. Handling custom key rules
            foreach (self::DIRECT_MAP as $customKey => $laravelKey) {
                $val = $rules[$customKey] ?? ''; // Jika tidak ada atau null, jadi string kosong

                if ($val !== '') {
                    $mappedRules[] = "{$laravelKey}:{$val}";
                }
            }

            // 3. Handling email rule
            if (!empty($rules['email'])) {
                $mappedRules[] = 'email';
            }

            // 4. Special Case: Regex
            // Laravel butuh delimiter (biasanya /) agar tidak error saat diproses
            if (!empty($rules['regex'])) {
                // Gabungkan trim dan penyambungan string dalam satu eksekusi
                $mappedRules[] = 'regex:/' . trim($rules['regex'], '/') . '/';
            }

            $laravelRules[$fieldName] = $mappedRules;
        }

        return $laravelRules;
    }
}

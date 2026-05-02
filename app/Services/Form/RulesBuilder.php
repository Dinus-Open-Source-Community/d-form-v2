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
            $metadata = $field->metadata;
            if (is_object($metadata) && method_exists($metadata, 'get')) {
                $ruleSet = $metadata->get('rules') ?? [];
                $inputSubtype = $metadata->get('type') ?? null;
                $options = $metadata->get('options') ?? null;
            } else {
                $md = (array) $metadata;
                $ruleSet = $md['rules'] ?? [];
                $inputSubtype = $md['type'] ?? null;
                $options = $md['options'] ?? null;
            }
            if (!is_array($ruleSet)) {
                $ruleSet = [];
            }

            if ($field->input_type === 'input' && $inputSubtype === 'email') {
                $ruleSet['email'] = true;
            }

            if ($field->input_type === 'radio' && is_string($options) && $options !== '') {
                $ruleSet['in'] = $options;
            }

            if ($field->input_type === 'checkbox') {
                $ruleSet['is_checkbox'] = true;
                if (is_string($options) && $options !== '') {
                    $ruleSet['checkbox_in'] = $options;
                }
            }

            if ($field->input_type === 'selectInput') {
                $isMultiple = is_object($metadata) && method_exists($metadata, 'get')
                    ? $metadata->get('is_multiple')
                    : (isset($md['is_multiple']) ? $md['is_multiple'] : false);

                if (filter_var($isMultiple, FILTER_VALIDATE_BOOLEAN)) {
                    $ruleSet['is_multiple'] = true;
                }
            }

            return [
                (string) $field->name => $ruleSet,
            ];
        })->all();

        return $result;
    }

    /**
     * Mengonversi custom rules array menjadi Laravel Validation rules.
     * @param array<string, array<string, mixed>> $customRules
     * @return array<string, array<int, string>>
     */
    public static function build(array $customRules): array
    {
        $laravelRules = [];

        foreach ($customRules as $fieldName => $rules) {
            $mappedRules = [];

            $isRequired   = filter_var($rules['required'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $isCheckbox   = !empty($rules['is_checkbox']);
            $isMultiple   = !empty($rules['is_multiple']);

            if ($isCheckbox) {
                $mappedRules[] = $isRequired ? 'required' : 'nullable';
                $mappedRules[] = 'array';
                if (!empty($rules['checkbox_in'])) {
                    $allowedValues = implode(',', array_map('trim', explode(',', (string) $rules['checkbox_in'])));
                    $laravelRules["{$fieldName}.*"] = ["string", "in:{$allowedValues}"];
                }
                $laravelRules[$fieldName] = $mappedRules;
                continue;
            }

            if ($isMultiple) {
                $mappedRules[] = $isRequired ? 'required' : 'nullable';
                $mappedRules[] = 'array';
                $laravelRules[$fieldName] = $mappedRules;
                continue;
            }

            if ($isRequired) {
                $mappedRules[] = 'required';
            } else {
                $mappedRules[] = 'nullable';
            }

            foreach (self::DIRECT_MAP as $customKey => $laravelKey) {
                $val = $rules[$customKey] ?? '';
                if ($val !== '') {
                    $mappedRules[] = "{$laravelKey}:{$val}";
                }
            }

            if (!empty($rules['email'])) {
                $mappedRules[] = 'email';
            }

            if (!empty($rules['regex'])) {
                $mappedRules[] = 'regex:/' . trim($rules['regex'], '/') . '/';
            }

            if (!empty($rules['in'])) {
                $allowedValues = implode(',', array_map('trim', explode(',', (string) $rules['in'])));
                $mappedRules[] = "in:{$allowedValues}";
            }

            $laravelRules[$fieldName] = $mappedRules;
        }

        return $laravelRules;
    }
}

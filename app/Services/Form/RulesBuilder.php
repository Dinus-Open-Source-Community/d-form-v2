<?php

namespace App\Services\Form;

use App\Models\FormField;
use App\Rules\SafeUploadFile;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

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
     * Builder block types that never produce an answer. Frontend single source:
     * `resources/js/lib/formFieldContract.ts`; both sides are asserted against
     * `tests/fixtures/form-field-contract.json`.
     *
     * @var list<string>
     */
    public const DISPLAY_ONLY_BUILDER_TYPES = ['heading', 'paragraph', 'divider', 'banner', 'image'];

    /**
     * Field whose validated key must never reach `form_answers.answers`.
     * Display-only blocks are skipped here so client-sent strings for them are
     * not even validated, let alone stored.
     */
    public static function isDisplayOnlyField(FormField $field): bool
    {
        $builderType = self::metadataArray($field)['builderType'] ?? null;

        if (is_string($builderType) && in_array($builderType, self::DISPLAY_ONLY_BUILDER_TYPES, true)) {
            return true;
        }

        // Legacy rows may still carry the (now rejected) `banner` input type.
        return $field->input_type === 'banner';
    }

    /**
     * Method ini untuk mengambil rules dari field-field untuk dibuild nantinya
     * @template T of \App\Models\FormField
     * @param Collection<int, T> $fields
     *
     */
    public static function extractRulesFromFields(Collection $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            if (self::isDisplayOnlyField($field)) {
                continue;
            }

            $metadata = self::metadataArray($field);
            $ruleSet = $metadata['rules'] ?? [];
            $inputSubtype = $metadata['type'] ?? null;
            $options = $metadata['options'] ?? null;
            $builderType = $metadata['builderType'] ?? null;

            if (!is_array($ruleSet)) {
                $ruleSet = [];
            }

            if ($field->input_type === 'input' && $inputSubtype === 'email') {
                $ruleSet['email'] = true;
            }

            if ($field->input_type === 'input' && $builderType === 'url') {
                $ruleSet['url'] = true;
            }

            if ($field->input_type === 'radio' && is_string($options) && $options !== '') {
                $ruleSet['in'] = $options;
            }

            // Canonical option sources: builder writes rules.in + optionChoices,
            // older forms only have metadata.options.
            $choices = self::resolveChoices($metadata, $ruleSet);

            if ($field->input_type === 'checkbox') {
                $ruleSet['is_checkbox'] = true;
                if ($choices !== []) {
                    $ruleSet['allowed_choices'] = $choices;
                }
            }

            if ($field->input_type === 'selectInput') {
                $isMultiple = filter_var($metadata['is_multiple'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($isMultiple) {
                    $ruleSet['is_multiple'] = true;
                    if ($choices !== []) {
                        $ruleSet['allowed_choices'] = $choices;
                    }
                }
            }

            if (
                in_array($field->input_type, ['selectInput', 'radio'], true)
                && self::choicesAreNumeric($choices)
            ) {
                $ruleSet['scalar_guard'] = 'numeric';
            }

            if ($field->input_type === 'datePicker') {
                $ruleSet['scalar_guard'] = 'date';
            }

            if ($field->input_type === 'input' && $inputSubtype === 'number') {
                $ruleSet['scalar_guard'] = 'numeric';
            }

            if ($field->input_type === 'fileUpload') {
                $ruleSet['is_file'] = true;
                if (array_key_exists('allowed_mime_types', $metadata)) {
                    $ruleSet['allowed_mime_types'] = $metadata['allowed_mime_types'];
                } elseif (is_string($builderType)) {
                    $defaultMimes = self::defaultUploadMimeTypes($builderType);
                    if ($defaultMimes !== []) {
                        $ruleSet['allowed_mime_types'] = $defaultMimes;
                    }
                }
                $isMultipleUpload = filter_var(
                    $metadata['multiple'] ?? $metadata['is_multiple'] ?? false,
                    FILTER_VALIDATE_BOOLEAN,
                );
                if ($isMultipleUpload) {
                    $ruleSet['is_multiple_file'] = true;
                    if (array_key_exists('max_files', $metadata)) {
                        $ruleSet['max_files'] = $metadata['max_files'];
                    }
                    if (array_key_exists('max_total_size_kb', $metadata)) {
                        $ruleSet['max_total_size_kb'] = $metadata['max_total_size_kb'];
                    }
                }
            }

            $result[(string) $field->name] = $ruleSet;
        }

        return $result;
    }

    /**
     * Mengonversi custom rules array menjadi Laravel Validation rules.
     *
     * @param array<string, array<string, mixed>> $customRules
     * @param bool $requireScalarString Tambahkan guard tipe skalar untuk field
     *        agar payload array/objek tidak lolos `required` lalu tersimpan
     *        sebagai array. Guard mengikuti tipe field (`string`, `numeric`
     *        untuk select/number numerik, `date` untuk datePicker). Default aman
     *        untuk jawaban user; `false` hanya untuk caller yang memang butuh
     *        rules mentah (mis. payload non-jawaban).
     * @return array<string, array<int, string|Closure|\App\Rules\SafeUploadFile|\Illuminate\Validation\Rules\In>>
     */
    public static function build(array $customRules, bool $requireScalarString = true): array
    {
        $laravelRules = [];

        foreach ($customRules as $fieldName => $rules) {
            $mappedRules = [];

            $isRequired   = filter_var($rules['required'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $isCheckbox   = !empty($rules['is_checkbox']);
            $isMultiple   = !empty($rules['is_multiple']);
            $isMultipleFile = !empty($rules['is_multiple_file']);

            if ($isCheckbox) {
                $mappedRules[] = $isRequired ? 'required' : 'nullable';
                $mappedRules[] = 'array';
                $laravelRules["{$fieldName}.*"] = self::elementRules($rules);
                $laravelRules[$fieldName] = $mappedRules;
                continue;
            }

            if ($isMultiple) {
                $mappedRules[] = $isRequired ? 'required' : 'nullable';
                $mappedRules[] = 'array';
                $laravelRules["{$fieldName}.*"] = self::elementRules($rules);
                $laravelRules[$fieldName] = $mappedRules;
                continue;
            }

            $isFile = ! empty($rules['is_file']);

            if ($isFile) {
                $mappedRules = self::fileRules($rules, $isRequired, $isMultipleFile);
                $laravelRules[$fieldName] = $mappedRules;
                if ($isMultipleFile) {
                    $laravelRules["{$fieldName}.*"] = self::fileElementRules($rules);
                }
                continue;
            }

            if ($isRequired) {
                $mappedRules[] = 'required';
            } else {
                $mappedRules[] = 'nullable';
            }

            if ($requireScalarString) {
                $mappedRules[] = self::scalarGuardRule($rules);
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

            if (!empty($rules['url'])) {
                $mappedRules[] = 'url';
            }

            if (!empty($rules['regex'])) {
                $pattern = trim((string) $rules['regex'], '/');
                $mappedRules[] = 'regex:/'.str_replace('/', '\/', $pattern).'/';
            }

            if (!empty($rules['in'])) {
                $allowedValues = self::normalizeChoiceList($rules['in']);
                if ($allowedValues !== []) {
                    $mappedRules[] = Rule::in($allowedValues);
                }
            }

            $laravelRules[$fieldName] = $mappedRules;
        }

        return $laravelRules;
    }

    /**
     * @param array<string, mixed> $rules
     * @return array<int, string|Closure>
     */
    private static function fileRules(array $rules, bool $required, bool $multiple): array
    {
        $mappedRules = [$required ? 'required' : 'nullable'];

        if (! $multiple) {
            $mappedRules[] = 'file';

            return array_merge($mappedRules, [self::safeUploadRule($rules)]);
        }

        $mappedRules[] = 'array';
        $mappedRules[] = 'max:'.self::boundedMaxFiles($rules['max_files'] ?? null);
        $mappedRules[] = self::totalSizeRule(self::boundedMaxTotalSizeKb($rules['max_total_size_kb'] ?? null));

        return $mappedRules;
    }

    /**
     * @param array<string, mixed> $rules
     * @return array<int, string|SafeUploadFile>
     */
    private static function fileElementRules(array $rules): array
    {
        return ['file', self::safeUploadRule($rules)];
    }

    /** @param array<string, mixed> $rules */
    private static function safeUploadRule(array $rules): SafeUploadFile
    {
        $extensions = self::extensionsFromRules($rules);

        return new SafeUploadFile(
            $extensions !== [] ? $extensions : SafeUploadFile::DEFAULT_FORMATS,
            is_numeric($rules['max_size'] ?? null) ? (int) $rules['max_size'] : null,
        );
    }

    /** @param array<string, mixed> $rules @return list<string> */
    private static function extensionsFromRules(array $rules): array
    {
        $mimeTypes = self::normalizeMimeTypes($rules['allowed_mime_types'] ?? null);
        $extensions = [];
        foreach ($mimeTypes as $mimeType) {
            $extensions = array_merge($extensions, match ($mimeType) {
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'image/webp' => ['webp'],
                'image/gif' => ['gif'],
                'application/pdf' => ['pdf'],
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
                'text/csv' => ['csv'],
                'text/plain' => ['txt'],
                default => [],
            });
        }
        if ($extensions !== []) {
            return array_values(array_unique($extensions));
        }

        $mimesVal = $rules['mimes'] ?? config('events.forms.upload_allowed_mimes', 'pdf,jpg,jpeg,png,webp,gif');
        if (! is_string($mimesVal) || trim($mimesVal) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $extension): string => strtolower(trim($extension)),
            explode(',', str_replace(' ', '', $mimesVal)),
        )));
    }

    /** @return list<string> */
    private static function normalizeMimeTypes(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $mime): bool => is_string($mime) && trim($mime) !== ''));
    }

    /** @return list<string> */
    private static function defaultUploadMimeTypes(string $builderType): array
    {
        return match ($builderType) {
            'image_upload' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'file_upload' => [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/csv',
                'text/plain',
            ],
            default => [],
        };
    }

    private static function boundedMaxFiles(mixed $value): int
    {
        $maximum = max(1, min(5, (int) config('events.forms.upload_max_files', 5)));
        $configured = is_numeric($value) ? (int) $value : $maximum;

        return max(1, min($maximum, $configured));
    }

    private static function boundedMaxTotalSizeKb(mixed $value): int
    {
        $maximum = max(1, min(20 * 1024, (int) config('events.forms.upload_max_total_size_kb', 20 * 1024)));
        $configured = is_numeric($value) ? (int) $value : $maximum;

        return max(1, min($maximum, $configured));
    }

    private static function totalSizeRule(int $maxTotalSizeKb): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($maxTotalSizeKb): void {
            if (! is_array($value)) {
                return;
            }

            $totalBytes = 0;
            foreach ($value as $file) {
                if ($file instanceof UploadedFile) {
                    $totalBytes += (int) ($file->getSize() ?? 0);
                }
            }

            if ($totalBytes > $maxTotalSizeKb * 1024) {
                $fail('The total uploaded files may not be greater than '.self::formatSizeLimit($maxTotalSizeKb).'.');
            }
        };
    }

    private static function formatSizeLimit(int $kilobytes): string
    {
        return intdiv($kilobytes, 1024).' MB';
    }

    /**
     * @return array<string, mixed>
     */
    private static function metadataArray(FormField $field): array
    {
        $metadata = $field->metadata;

        if ($metadata instanceof Collection) {
            return $metadata->all();
        }

        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            return $metadata->toArray();
        }

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * Rules for one element of an array-valued field (`checkbox`, multi-select).
     * The whitelist uses array-form `Rule::in` so an option label containing
     * `,` or `|` cannot widen/inject the rule.
     *
     * @param  array<string, mixed>  $rules
     * @return array<int, string|\Illuminate\Validation\Rules\In>
     */
    private static function elementRules(array $rules): array
    {
        $allowedValues = self::normalizeChoiceList($rules['allowed_choices'] ?? null);

        return $allowedValues === [] ? ['string'] : ['string', Rule::in($allowedValues)];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $ruleSet
     * @return list<string>
     */
    private static function resolveChoices(array $metadata, array $ruleSet): array
    {
        $choices = self::normalizeChoiceList($ruleSet['in'] ?? null);
        if ($choices === []) {
            $choices = self::normalizeChoiceList($metadata['optionChoices'] ?? null);
        }
        if ($choices === []) {
            $choices = self::normalizeChoiceList($metadata['options'] ?? null);
        }

        return $choices;
    }

    /**
     * @param  list<string>  $choices
     */
    private static function choicesAreNumeric(array $choices): bool
    {
        if ($choices === []) {
            return false;
        }

        foreach ($choices as $choice) {
            if (!is_numeric($choice)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scalar guard for a field: rejects array/object payloads while keeping the
     * field's natural scalar type (numeric select/number, date).
     *
     * @param  array<string, mixed>  $rules
     */
    private static function scalarGuardRule(array $rules): string
    {
        $guard = $rules['scalar_guard'] ?? 'string';

        return in_array($guard, ['string', 'numeric', 'date'], true) ? $guard : 'string';
    }

    /**
     * Normalize any option source into a list of allowed answer strings.
     *
     * Accepts a CSV string, a JSON string, an array of strings, or an array of
     * option rows (`optionChoices`) where the answer is the `label` (falling
     * back to `value`/`text`/`name`).
     *
     * @return list<string>
     */
    private static function normalizeChoiceList(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }

            if (is_string($value)) {
                return self::uniqueChoices(explode(',', $value));
            }
        }

        if (!is_array($value)) {
            return [];
        }

        return self::uniqueChoices($value);
    }

    /**
     * @param array<int|string, mixed> $items
     * @return list<string>
     */
    private static function uniqueChoices(array $items): array
    {
        $choices = [];

        foreach ($items as $item) {
            $label = self::choiceLabel($item);
            if ($label !== null) {
                $choices[$label] = true;
            }
        }

        return array_keys($choices);
    }

    private static function choiceLabel(mixed $item): ?string
    {
        if (is_string($item) || is_int($item) || is_float($item)) {
            $label = trim((string) $item);

            return $label === '' ? null : $label;
        }

        if (!is_array($item)) {
            return null;
        }

        foreach (['label', 'value', 'text', 'name'] as $key) {
            $candidate = $item[$key] ?? null;
            if (is_string($candidate) || is_int($candidate) || is_float($candidate)) {
                $label = trim((string) $candidate);
                if ($label !== '') {
                    return $label;
                }
            }
        }

        return null;
    }
}

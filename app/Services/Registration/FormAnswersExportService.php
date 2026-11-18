<?php

namespace App\Services\Registration;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Services\Form\RulesBuilder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bentuk data bersama untuk export jawaban form: definisi kolom, baris CSV,
 * daftar lampiran, dan ringkasan per pengirim. Dipakai controller CSV dan ZIP
 * agar kedua format tidak bisa drift.
 */
final class FormAnswersExportService
{
    private const ARRAY_SEPARATOR = ' | ';

    public function __construct(
        private readonly FormAnswerFileStorage $files,
        private readonly BundleGuestDisplayNameResolver $displayNames,
        private readonly FormAnswerRecipientResolver $recipients,
    ) {
    }

    /**
     * Kolom jawaban yang benar-benar menyimpan data, urut sesuai form.
     *
     * @return list<array{name: string, label: string, input_type: string}>
     */
    public function fields(Form $form): array
    {
        return $form->formFields()
            ->orderBy('order')
            ->get(['name', 'label', 'input_type', 'metadata'])
            ->filter(fn (FormField $field): bool => ! $this->isDisplayOnly($field))
            ->map(fn (FormField $field): array => [
                'name' => (string) $field->name,
                'label' => (string) $field->label,
                'input_type' => (string) $field->input_type,
            ])
            ->values()
            ->all();
    }

    /** @return Builder<FormAnswer> */
    public function query(Form $form): Builder
    {
        return FormAnswer::query()
            ->with(['user:id,name,email'])
            ->where('form_id', $form->id)
            ->whereListedForOrganizerParticipantRoster()
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @param  list<array{name: string, label: string, input_type: string}>  $fields
     * @return list<string>
     */
    public function csvHeader(array $fields): array
    {
        return [
            'submission_id',
            'submitted_at',
            'review_status',
            'user_name',
            'user_email',
            'registration_code',
            'registration_role',
            'member_confirmation_status',
            'group_token',
            ...$this->uniqueLabels($fields),
        ];
    }

    /**
     * @param  list<array{name: string, label: string, input_type: string}>  $fields
     * @return list<string>
     */
    public function csvRow(FormAnswer $answer, array $fields): array
    {
        $answers = is_array($answer->answers) ? $answer->answers : [];

        return [
            (string) $answer->id,
            $answer->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
            $answer->review_status?->value ?? '',
            $answer->user?->name ?? '',
            $answer->user?->email ?? '',
            (string) ($answer->registration_code ?? ''),
            $answer->registration_role?->value ?? '',
            $answer->member_confirmation_status?->value ?? '',
            $answer->group_token ?? '',
            ...array_map(
                fn (array $field): string => $this->csvValue($answer, $field, $answers[$field['name']] ?? null),
                $fields,
            ),
        ];
    }

    /**
     * Nama folder pengirim: nomor urut, nama tampilan, dan email bila ada.
     * Nomor urut menjaga dua peserta bernama sama tetap berada di folder berbeda.
     *
     * @param  list<array{name: string, label: string, input_type: string}>  $fields
     */
    public function folderName(FormAnswer $answer, int $index, array $fields): string
    {
        $userName = $answer->user?->name;
        $name = is_string($userName) && trim($userName) !== ''
            ? trim($userName)
            : ($this->preferredAnswerName($answer, $fields) ?? $this->displayNames->resolve($answer));
        $email = $this->recipients->email($answer);
        $raw = sprintf('%03d - %s', $index + 1, $email === null ? $name : $name.' - '.$email);

        return $this->sanitizeZipSegment($raw, 'participant-'.($index + 1));
    }

    /** Nama file di dalam folder; ekstensi dipertahankan bila memungkinkan. */
    public function fileName(string $path): string
    {
        $base = basename($path);

        return $this->sanitizeZipSegment($base, 'file');
    }

    /**
     * Lampiran yang benar-benar ada di disk untuk satu jawaban.
     *
     * @param  list<array{name: string, label: string, input_type: string}>  $fields
     * @return list<array{field: string, label: string, disk: string, path: string, filename: string}>
     */
    public function fileEntries(FormAnswer $answer, array $fields): array
    {
        $answers = is_array($answer->answers) ? $answer->answers : [];
        $entries = [];

        foreach ($fields as $field) {
            if ($field['input_type'] !== 'fileUpload') {
                continue;
            }

            $value = $answers[$field['name']] ?? null;
            $paths = is_array($value) ? $value : [$value];

            foreach ($paths as $path) {
                if (! is_string($path) || $path === '') {
                    continue;
                }

                $located = $this->files->locate($answer, $field['name'], basename($path));
                if ($located === null) {
                    continue;
                }

                $entries[] = [
                    'field' => $field['name'],
                    'label' => $field['label'],
                    'disk' => $located['disk'],
                    'path' => $located['path'],
                    'filename' => $this->fileName($located['path']),
                ];
            }
        }

        return $entries;
    }

    /**
     * Guest tidak punya kolom nama; pakai field yang labelnya mengandung
     * "nama"/"name" agar folder tidak jatuh ke nilai pertama yang kebetulan
     * tersimpan lebih dulu (MySQL JSON mengurutkan key).
     *
     * @param  list<array{name: string, label: string, input_type: string}>  $fields
     */
    private function preferredAnswerName(FormAnswer $answer, array $fields): ?string
    {
        $answers = is_array($answer->answers) ? $answer->answers : [];

        foreach ($fields as $field) {
            if ($field['input_type'] === 'fileUpload' || preg_match('/nama|name/iu', $field['label']) !== 1) {
                continue;
            }

            $value = $answers[$field['name']] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function isDisplayOnly(FormField $field): bool
    {
        $metadata = $field->metadata;
        $builderType = is_array($metadata) ? ($metadata['builderType'] ?? null) : null;

        return is_string($builderType) && in_array($builderType, RulesBuilder::DISPLAY_ONLY_BUILDER_TYPES, true);
    }

    /**
     * @param  array{name: string, label: string, input_type: string}  $field
     */
    private function csvValue(FormAnswer $answer, array $field, mixed $value): string
    {
        if ($field['input_type'] === 'fileUpload') {
            $paths = is_array($value) ? $value : [$value];
            $urls = [];

            foreach ($paths as $path) {
                if (! is_string($path) || $path === '') {
                    continue;
                }

                $located = $this->files->locate($answer, $field['name'], basename($path));
                if ($located === null) {
                    continue;
                }

                $urls[] = $this->files->temporaryUrl($answer, $field['name'], basename($located['path']));
            }

            return implode(self::ARRAY_SEPARATOR, $urls);
        }

        return $this->plainValue($value);
    }

    private function plainValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_array($value)) {
            return implode(
                self::ARRAY_SEPARATOR,
                array_map(static fn (mixed $item): string => (string) $item, $value),
            );
        }

        return (string) $value;
    }

    /**
     * @param  list<array{name: string, label: string, input_type: string}>  $fields
     * @return list<string>
     */
    private function uniqueLabels(array $fields): array
    {
        $seen = [];
        $labels = [];

        foreach ($fields as $field) {
            $label = $field['label'] === '' ? $field['name'] : $field['label'];
            $count = ($seen[$label] ?? 0) + 1;
            $seen[$label] = $count;
            $labels[] = $count === 1 ? $label : $label.' ('.$count.')';
        }

        return $labels;
    }

    private function sanitizeZipSegment(string $value, string $fallback): string
    {
        $cleaned = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $cleaned = trim((string) preg_replace('/\s+/u', ' ', $cleaned));

        if ($cleaned === '' || $cleaned === '.' || $cleaned === '..') {
            return $fallback;
        }

        return mb_substr($cleaned, 0, 120);
    }
}

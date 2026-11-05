<?php

namespace App\Services\Registration;

use App\Models\FormAnswer;
use App\Models\FormField;

final class RegistrationAnswersSummarizer
{
    public function __construct(private readonly FormAnswerFileStorage $files)
    {
    }

    /**
     * Summary for the participant portal: file links use the authenticated route.
     *
     * @return array<string, string>
     */
    public function summarizeForPortal(FormAnswer $submission): array
    {
        return $this->buildSummary($submission, temporaryFileUrls: false);
    }

    /**
     * Summary for email and CSV export: file links are temporary signed URLs so
     * they still work for guest recipients who have no session.
     *
     * @return array<string, string>
     */
    public function summarize(FormAnswer $submission): array
    {
        return $this->buildSummary($submission, temporaryFileUrls: true);
    }

    /**
     * @return array<string, string>
     */
    private function buildSummary(FormAnswer $submission, bool $temporaryFileUrls): array
    {
        $answers = is_array($submission->answers) ? $submission->answers : [];

        $fields = FormField::query()
            ->where('form_id', $submission->form_id)
            ->orderBy('order')
            ->get(['name', 'label', 'input_type', 'metadata']);

        $lines = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field->name, $answers)) {
                continue;
            }
            $value = $answers[$field->name];

            if ($field->input_type === 'fileUpload') {
                $lines[$field->label] = $this->fileFieldSummary(
                    $submission,
                    $field->name,
                    $value,
                    $temporaryFileUrls,
                );

                continue;
            }

            if (is_array($value)) {
                $lines[$field->label] = implode(', ', array_map(fn ($v) => (string) $v, $value));

                continue;
            }

            if ($value === null || $value === '') {
                $lines[$field->label] = '—';
            } else {
                $lines[$field->label] = (string) $value;
            }
        }

        return $lines;
    }

    private function fileFieldSummary(
        FormAnswer $submission,
        string $field,
        mixed $value,
        bool $temporary,
    ): string {
        if (! is_string($value) || $value === '') {
            return '—';
        }

        try {
            return $temporary
                ? $this->files->temporaryUrl($submission, $field)
                : $this->files->relativeUrl($submission, $field);
        } catch (\Throwable) {
            return __('File uploaded');
        }
    }
}

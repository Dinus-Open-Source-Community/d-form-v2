<?php

namespace App\Services\Registration;

use App\Models\FormAnswer;
use App\Models\FormField;

final class RegistrationAnswersSummarizer
{
    /**
     * @return array<string, string>
     */
    public function summarize(FormAnswer $submission): array
    {
        $answers = is_array($submission->answers) ? $submission->answers : [];

        $fields = FormField::query()
            ->where('form_id', $submission->form_id)
            ->orderBy('order')
            ->get(['name', 'label', 'input_type']);

        $lines = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field->name, $answers)) {
                continue;
            }
            $value = $answers[$field->name];

            if ($field->input_type === 'fileUpload') {
                $lines[$field->label] = is_string($value) && $value !== ''
                    ? __('File uploaded')
                    : '—';

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
}

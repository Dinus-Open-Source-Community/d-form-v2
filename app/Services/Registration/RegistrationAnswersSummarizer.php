<?php

namespace App\Services\Registration;

use App\Models\FormAnswer;
use App\Models\FormField;
use Illuminate\Database\Eloquent\Collection;

final class RegistrationAnswersSummarizer
{
    private const ARRAY_VALUE_SEPARATOR = ', ';
    private const FILE_VALUE_SEPARATOR = '; ';

    /**
     * Field definitions memoized per form id for the lifetime of this instance.
     *
     * The summarizer is resolved once per request/export, so one export of N
     * submissions reads `form_fields` once per distinct form instead of N times.
     *
     * @var array<string, Collection<int, FormField>>
     */
    private array $fieldsByForm = [];

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

        $fields = $this->fieldsFor((string) $submission->form_id);

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
                $lines[$field->label] = implode(self::ARRAY_VALUE_SEPARATOR, array_map(fn (mixed $v): string => (string) $v, $value));

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

    /**
     * Field definitions are immutable for the lifetime of one summarizer
     * instance, so they are memoized per `form_id`.
     *
     * @return Collection<int, FormField>
     */
    private function fieldsFor(string $formId): Collection
    {
        return $this->fieldsByForm[$formId] ??= FormField::query()
            ->where('form_id', $formId)
            ->orderBy('order')
            ->get(['name', 'label', 'input_type', 'metadata']);
    }

    private function fileFieldSummary(
        FormAnswer $submission,
        string $field,
        mixed $value,
        bool $temporary,
    ): string {
        $hasValue = is_array($value)
            ? $value !== []
            : is_string($value) && $value !== '';
        if (! $hasValue) {
            return '—';
        }

        try {
            $serialized = $temporary
                ? $this->files->temporaryAnswerUrls($submission, [$field => $value], [$field])
                : $this->files->answerUrls($submission, [$field => $value], [$field]);
            $urls = $serialized[$field] ?? null;
            $values = is_array($urls) ? $urls : [$urls];
            $urls = array_values(array_filter($values, 'is_string'));

            return $urls === [] ? __('File uploaded') : implode(self::FILE_VALUE_SEPARATOR, $urls);
        } catch (\Throwable) {
            return __('File uploaded');
        }
    }
}

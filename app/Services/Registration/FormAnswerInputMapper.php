<?php

namespace App\Services\Registration;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Maps validated submission input to the `form_answers.answers` payload.
 *
 * Only keys declared by the validator are read; unvalidated request keys never
 * reach a stored answer.
 */
final class FormAnswerInputMapper
{
    public function __construct(private readonly FormAnswerFileStorage $fileStorage)
    {
    }

    /**
     * @param  array<string, mixed>  $validated  output of `Validator::validated()`
     * @param  Collection<int, FormField>  $fields  ordered form fields
     * @param  int  $extraMemberSlots  bundle participant slots beyond the leader
     * @return array{leader: array<string, mixed>, members: list<array<string, mixed>>}
     */
    public function map(array $validated, Collection $fields, Form $form, int $extraMemberSlots = 0): array
    {
        $leader = $this->mapLeaderAnswers($validated, $fields, $form);

        if ($extraMemberSlots < 1) {
            return ['leader' => $leader, 'members' => []];
        }

        $duplicatable = array_flip(BundleSubmissionRules::duplicatableFieldNames($fields));
        /** @var list<array<string, mixed>> $members */
        $members = array_fill(0, $extraMemberSlots, []);

        foreach ($fields as $field) {
            if (BundleSubmissionRules::fieldIsDisplayOnly($field)) {
                continue;
            }

            $name = $field->name;

            if (! isset($duplicatable[$name])) {
                foreach (array_keys($members) as $index) {
                    $members[$index][$name] = $leader[$name];
                }

                continue;
            }

            for ($index = 0; $index < $extraMemberSlots; $index++) {
                $members[$index][$name] = $this->valueFor(
                    $validated,
                    $field,
                    $form,
                    BundleSubmissionRules::slotInputName($name, $index),
                );
            }
        }

        return ['leader' => $leader, 'members' => $members];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  Collection<int, FormField>  $fields
     * @return array<string, mixed>
     */
    private function mapLeaderAnswers(array $validated, Collection $fields, Form $form): array
    {
        $answers = [];

        foreach ($fields as $field) {
            if (BundleSubmissionRules::fieldIsDisplayOnly($field)) {
                continue;
            }

            $answers[$field->name] = $this->valueFor($validated, $field, $form, $field->name);
        }

        return $answers;
    }

    /**
     * Read one answer from validated input; dotted names resolve nested keys.
     *
     * @param  array<string, mixed>  $validated
     */
    private function valueFor(array $validated, FormField $field, Form $form, string $inputName): mixed
    {
        if ($field->input_type === 'fileUpload') {
            $file = Arr::get($validated, $inputName);

            return $file instanceof UploadedFile ? $this->fileStorage->store($file, $form) : null;
        }

        if ($field->input_type === 'checkbox') {
            return Arr::get($validated, $inputName, []);
        }

        if ($field->input_type === 'selectInput' && $this->isMultipleSelect($field)) {
            return Arr::get($validated, $inputName, []);
        }

        return Arr::get($validated, $inputName);
    }

    private function isMultipleSelect(FormField $field): bool
    {
        $metadata = $field->metadata;

        $isMultiple = $metadata instanceof Collection
            ? $metadata->get('is_multiple')
            : (is_array($metadata) ? ($metadata['is_multiple'] ?? null) : null);

        return filter_var($isMultiple, FILTER_VALIDATE_BOOLEAN);
    }
}

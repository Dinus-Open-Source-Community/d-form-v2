<?php

namespace App\Services\Du;

use App\Enums\FormPurpose;
use App\Models\DuCourse;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use Illuminate\Support\Collection;

/** Resolves the per-form class field and its DU course mapping from the cached catalog; never calls DU. */
final class DuFormCourseResolver
{
    /** The locked system field used by D-Kelas forms. */
    public const SYSTEM_FIELD_NAME = 'du_course_uid';

    public function __construct(
        private readonly DuCourseRepository $courses,
        private readonly DuCourseResolver $resolver,
    ) {
    }

    /** A form opts in through its purpose (D-Kelas) or through exactly one flagged custom field. */
    public function enabled(Form $form): bool
    {
        if ($form->purpose() === FormPurpose::DKelas) {
            return true;
        }

        return $this->fields($form)->contains(
            fn (FormField $field): bool => $this->isFlagged($field),
        );
    }

    /** The flagged custom field, or the system course field for a D-Kelas form. */
    public function classField(Form $form): ?FormField
    {
        $fields = $this->fields($form);

        if ($form->purpose() === FormPurpose::DKelas) {
            return $fields->first(
                fn (FormField $field): bool => $field->name === self::SYSTEM_FIELD_NAME,
            );
        }

        return $fields->first(
            fn (FormField $field): bool => $this->isFlagged($field),
        );
    }

    /** Course uid for one submission: the system field value or the immutable option value mapped to a cached course. */
    public function courseUidFor(FormAnswer $answer): ?string
    {
        $form = $answer->form;

        if (! $form instanceof Form) {
            return null;
        }

        // D-Kelas keys its class value by the fixed system field name, even without the field row.
        if ($form->purpose() === FormPurpose::DKelas) {
            $value = $this->answerValue($answer, self::SYSTEM_FIELD_NAME);

            return $value === null ? null : $this->resolver->uidForIssuance($value);
        }

        $field = $this->classField($form);

        if (! $field instanceof FormField) {
            return null;
        }

        $value = $this->answerValue($answer, $field->name);

        if ($value === null) {
            return null;
        }

        $mappedUid = $this->mappingFor($field)[$value]['course_uid'] ?? null;

        return $mappedUid !== null && $this->courses->findUniqueByValue($mappedUid, false)?->uid === $mappedUid
            ? $mappedUid
            : null;
    }

    /**
     * Exact unique active course by uid or title; null when zero or multiple match.
     * `$field` is reserved for future field-scoped suggestions; the current cache lookup is value-only.
     */
    public function suggest(FormField $field, string $value): ?DuCourse
    {
        return $this->courses->findUniqueByValue($value, true);
    }

    /**
     * Value to course index for one already-loaded catalog. Callers pass the active
     * catalog for suggestions and the full cached catalog for stored-answer mapping.
     * An exact uid wins; a title resolves only when exactly one course carries it.
     *
     * The drawer index is exact-case, while SQL title matching is collation-insensitive;
     * a case-variant title is a suggestion miss, never a wrong course.
     *
     * @param  Collection<int, DuCourse>  $courses
     * @return array<string, DuCourse|null>
     */
    public function activeSuggestions(Collection $courses): array
    {
        $byValue = [];
        $titleCounts = [];
        $titleCourses = [];

        foreach ($courses as $course) {
            $byValue[$course->uid] = $course;
            $titleCounts[$course->title] = ($titleCounts[$course->title] ?? 0) + 1;
            $titleCourses[$course->title] ??= $course;
        }

        foreach ($titleCourses as $title => $course) {
            if (! array_key_exists($title, $byValue)) {
                $byValue[$title] = $titleCounts[$title] === 1 ? $course : null;
            }
        }

        return $byValue;
    }

    /**
     * Option value to course mapping; entries without a string uid are ignored.
     *
     * @return array<string, array{course_uid: string, course_title: string}>
     */
    public function mappingFor(FormField $field): array
    {
        $raw = $this->metadata($field)['du_option_map'] ?? null;

        if ($raw instanceof Collection) {
            $raw = $raw->all();
        }

        if (! is_array($raw)) {
            return [];
        }

        $mapping = [];
        foreach ($raw as $optionValue => $entry) {
            // JSON decoding turns numeric-string option keys into ints; keep them instead of dropping the option.
            $value = is_int($optionValue) ? (string) $optionValue : $optionValue;

            if (! is_string($value) || ! is_array($entry)) {
                continue;
            }

            $courseUid = $entry['course_uid'] ?? null;
            if (! is_string($courseUid) || $courseUid === '') {
                continue;
            }

            $courseTitle = $entry['course_title'] ?? '';

            $mapping[$value] = [
                'course_uid' => $courseUid,
                'course_title' => is_string($courseTitle) ? $courseTitle : '',
            ];
        }

        return $mapping;
    }

    /**
     * Fields ordered for deterministic class-field selection; reuses the relation when already loaded.
     *
     * @return Collection<int, FormField>
     */
    private function fields(Form $form): Collection
    {
        $form->loadMissing('formFields');

        return $form->formFields->sortBy('order')->values();
    }

    private function answerValue(FormAnswer $answer, string $fieldName): ?string
    {
        $answers = $answer->answers;
        $value = is_array($answers) ? ($answers[$fieldName] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function isFlagged(FormField $field): bool
    {
        return ($this->metadata($field)['du_redeem_enabled'] ?? null) === true;
    }

    /** @return array<string, mixed> */
    private function metadata(FormField $field): array
    {
        $metadata = $field->metadata;

        if ($metadata instanceof Collection) {
            return $metadata->all();
        }

        return is_array($metadata) ? $metadata : [];
    }
}

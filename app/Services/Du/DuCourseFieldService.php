<?php

namespace App\Services\Du;

use App\Enums\FormPurpose;
use App\Models\DuCourse;
use App\Models\Form;
use App\Models\FormField;
use App\Support\FormFieldTypeMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DuCourseFieldService
{
    private const FIELD_NAME = 'du_course_uid';

    /** Satu-satunya sumber identitas field sistem; `ensure()` dan guard snapshot sama-sama memakainya. */
    private const SYSTEM_METADATA = [
        'system' => true,
        'locked' => true,
        'options_source' => 'du_catalog',
        'duplicatable' => true,
        'rules' => ['required' => true],
    ];

    public function __construct(private readonly DuCourseRepository $courses)
    {
    }

    /** Idempotently guarantees the locked D-Kelas course field, preserving any edited label and description. */
    public function ensure(Form $form): FormField
    {
        return DB::transaction(function () use ($form): FormField {
            $form = Form::query()->whereKey($form->getKey())->lockForUpdate()->firstOrFail();
            $field = $form->formFields()->withTrashed()->where('name', self::FIELD_NAME)->first();

            if ($field === null) {
                return $form->formFields()->create([
                    'input_type' => 'selectInput',
                    'label' => 'Kelas',
                    'name' => self::FIELD_NAME,
                    'metadata' => self::SYSTEM_METADATA,
                    'order' => 0,
                    'is_append' => $this->isAppendable($form),
                ]);
            }

            $wasTrashed = $field->trashed();
            if ($wasTrashed) {
                $field->restore();
            }

            $metadata = $field->metadata instanceof Collection ? $field->metadata->all() : (array) $field->metadata;
            $merged = array_replace_recursive($metadata, self::SYSTEM_METADATA);
            $appendable = $this->isAppendable($form);

            if ($wasTrashed
                || $field->input_type !== 'selectInput'
                || (bool) $field->is_append !== $appendable
                || $merged !== $metadata) {
                $field->fill([
                    'input_type' => 'selectInput',
                    'metadata' => $merged,
                    'is_append' => $appendable,
                ])->save();
            }

            return $field;
        });
    }

    /**
     * Forces the client field snapshot of a D-Kelas form back to the single system field identity.
     *
     * The builder may rename the locked field, change its type, or strip its metadata; only the
     * admin-editable label/description text is kept from the client row.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function enforceSnapshotIdentity(Form $form, array $rows): array
    {
        $systemField = $form->formFields()
            ->withTrashed()
            ->where('name', self::FIELD_NAME)
            ->first(['id', 'label', 'description', 'order']);
        $systemId = $systemField?->id;

        $claimed = array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->claimsSystemIdentity($row, $systemId),
        ));

        if ($claimed === []) {
            return $rows;
        }

        $forced = $this->systemFieldRow($systemField, $this->preferredSystemRow($claimed, $systemId), $this->isAppendable($form));

        $placed = false;
        $result = [];
        foreach ($rows as $row) {
            if (! $this->claimsSystemIdentity($row, $systemId)) {
                $result[] = $row;

                continue;
            }

            if (! $placed) {
                $result[] = $forced;
                $placed = true;
            }
        }

        return $result;
    }

    /**
     * Attaches cached catalog options to a D-Kelas course field payload; no upstream call.
     *
     * `metadata.selected_course_uids` filters the catalog per form: absent means every
     * active course (backward compatible), an empty array means no selectable course.
     * `$activeCourses` lets a request that already loaded the catalog avoid a second query;
     * it is never stored on this service (Octane safety).
     *
     * @param  array<string, mixed>  $field
     * @param  list<string>|null  $selectedUids
     * @param  Collection<int, DuCourse>|null  $activeCourses
     * @return array<string, mixed>
     */
    public function applyOptions(array $field, ?array $selectedUids = null, ?Collection $activeCourses = null): array
    {
        $metadata = $field['metadata'] ?? [];
        if (($field['name'] ?? null) !== self::FIELD_NAME
            || ! is_array($metadata)
            || ($metadata['options_source'] ?? null) !== 'du_catalog') {
            return $field;
        }

        $selection = $selectedUids ?? ($metadata['selected_course_uids'] ?? null);
        $courses = $activeCourses ?? $this->courses->active();

        if (is_array($selection)) {
            $allowed = array_values(array_filter($selection, 'is_string'));
            $courses = $courses
                ->filter(static fn (DuCourse $course): bool => in_array($course->uid, $allowed, true))
                ->values();
        }

        $field['metadata']['optionChoices'] = $courses
            ->map(static fn (DuCourse $course): array => [
                'type' => 'text',
                'label' => $course->title,
                'value' => $course->uid,
            ])->values()->all();
        unset($field['metadata']['options']);

        return $field;
    }

    /**
     * Active UID/title values a participant may submit for the system course field.
     *
     * Null means the form has no explicit `metadata.selected_course_uids` (every active
     * course); an empty array means the form currently offers no selectable course.
     *
     * @return list<string>|null
     */
    public function selectableValues(Form $form): ?array
    {
        $form->loadMissing('formFields');
        $field = $form->formFields->firstWhere('name', self::FIELD_NAME);

        if (! $field instanceof FormField) {
            return null;
        }

        $selection = $this->metadata($field)['selected_course_uids'] ?? null;
        if (! is_array($selection)) {
            return null;
        }

        $selected = array_values(array_filter($selection, 'is_string'));

        if ($selected === []) {
            return [];
        }

        return $this->courses->active()
            ->filter(static fn (DuCourse $course): bool => in_array($course->uid, $selected, true))
            ->flatMap(static fn (DuCourse $course): array => [$course->uid, $course->title])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The union of the field's current option labels: `optionChoices` labels, then the
     * legacy `rules.in` CSV, then the legacy `options` list. A union (not a first-source
     * fallback) keeps every label any answer-validation source accepts mappable.
     *
     * @return list<string>
     */
    public function optionLabels(FormField $field): array
    {
        $metadata = $this->metadata($field);
        $rules = is_array($metadata['rules'] ?? null) ? $metadata['rules'] : [];

        return array_values(array_unique([
            ...$this->labelsFromChoices($metadata['optionChoices'] ?? null),
            ...$this->labelsFromCsv($rules['in'] ?? null),
            ...$this->labelsFromChoices($metadata['options'] ?? null),
        ]));
    }

    /**
     * Restores the stored option content of every flagged field before a snapshot write.
     *
     * While `metadata.du_redeem_enabled` is true the option values are the mapping key and
     * the stored answer value, so type, name, options, `rules.in`, and single/multi mode
     * stay frozen; the admin may still change the label, description, order, mapping, and
     * the flag itself. Disabling the flag narrows the lock to the field identity, which is
     * the documented way to edit the fixed options. Deleting the field or flagging a second
     * field is rejected, and a row recreated with a new id is still matched by its stored name.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function enforceLockedOptionContent(Form $form, array $rows): array
    {
        // Validation accepts the boolean grammar (true/false, 1/0, "1"/"0"); guards compare
        // canonical booleans, so normalize once before any check.
        $rows = $this->normalizeRedeemFlags($rows);

        $flagged = array_filter(
            $rows,
            static fn (array $row): bool => (($row['metadata']['du_redeem_enabled'] ?? null) === true),
        );

        if (count($flagged) > 1) {
            throw ValidationException::withMessages([
                'fields' => 'Hanya satu field kelas DU yang boleh aktif.',
            ]);
        }

        foreach ($flagged as $row) {
            $type = $row['type'] ?? null;

            if ($type !== 'select' && $type !== 'radio') {
                throw ValidationException::withMessages([
                    'fields' => 'Redeem code DU hanya untuk field pilihan (select atau radio).',
                ]);
            }
        }

        $locked = FormField::query()
            ->where('form_id', $form->id)
            ->get(['id', 'input_type', 'name', 'metadata'])
            ->filter(fn (FormField $field): bool => $this->isRedeemEnabled($field))
            ->values();

        if ($locked->isEmpty()) {
            return $rows;
        }

        foreach ($locked as $stored) {
            if (! $this->hasStoredField($rows, $stored)) {
                throw ValidationException::withMessages([
                    'fields' => 'Field kelas DU tidak dapat dihapus.',
                ]);
            }
        }

        foreach ($rows as $index => $row) {
            $stored = $this->matchLockedField($row, $locked);

            if ($stored === null) {
                continue;
            }

            $freezeOptions = ($row['metadata']['du_redeem_enabled'] ?? null) !== false;

            $rows[$index] = $this->lockedRow($row, $stored, $freezeOptions);
        }

        return $rows;
    }

    /**
     * A custom (non-system) field currently carries the redeem flag.
     *
     * D-Kelas always uses the system field, so converting a registration form to D-Kelas
     * while a custom field still carries the flag would freeze options nobody can unlock.
     */
    public function hasFlaggedCustomField(Form $form): bool
    {
        return $form->formFields()
            ->where('name', '!=', self::FIELD_NAME)
            ->get(['name', 'metadata'])
            ->contains(fn (FormField $field): bool => $this->isRedeemEnabled($field));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRedeemFlags(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $metadata = $row['metadata'] ?? null;

            if (! is_array($metadata) || ! array_key_exists('du_redeem_enabled', $metadata)) {
                continue;
            }

            $flag = $this->normalizeFlag($metadata['du_redeem_enabled']);

            // An unparseable value is treated as "not provided" so the stored flag survives.
            if ($flag === null) {
                unset($rows[$index]['metadata']['du_redeem_enabled']);

                continue;
            }

            $rows[$index]['metadata']['du_redeem_enabled'] = $flag;
        }

        return $rows;
    }

    private function normalizeFlag(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($normalized) ? $normalized : null;
    }

    private function isRedeemEnabled(FormField $field): bool
    {
        return $this->normalizeFlag($this->metadata($field)['du_redeem_enabled'] ?? null) === true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function hasStoredField(array $rows, FormField $stored): bool
    {
        foreach ($rows as $row) {
            if ($this->matchesStoredField($row, $stored)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<int, FormField>  $locked
     */
    private function matchLockedField(array $row, Collection $locked): ?FormField
    {
        foreach ($locked as $stored) {
            if ($this->matchesStoredField($row, $stored)) {
                return $stored;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    private function matchesStoredField(array $row, FormField $stored): bool
    {
        $id = $row['id'] ?? null;
        if (is_string($id) && $id === (string) $stored->getKey()) {
            return true;
        }

        return ($row['name'] ?? null) === (string) $stored->name;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function lockedRow(array $row, FormField $stored, bool $freezeOptions = true): array
    {
        $storedMetadata = $this->metadata($stored);
        $incoming = $row['metadata'] ?? [];
        $incoming = is_array($incoming) ? $incoming : [];

        // array_replace keeps stored-only metadata (e.g. the flag) and lets the owner
        // change the mapping; content keys are forced back afterwards.
        $metadata = array_replace($storedMetadata, $incoming);

        $forced = ['is_multiple'];
        if ($freezeOptions) {
            $forced = ['optionChoices', 'options', 'is_multiple'];
        }

        foreach ($forced as $key) {
            if (array_key_exists($key, $storedMetadata)) {
                $metadata[$key] = $storedMetadata[$key];
            } else {
                unset($metadata[$key]);
            }
        }

        $storedRules = is_array($storedMetadata['rules'] ?? null) ? $storedMetadata['rules'] : [];
        $rules = is_array($metadata['rules'] ?? null) ? $metadata['rules'] : [];

        $forcedRules = $freezeOptions ? ['in', 'multiple'] : ['multiple'];
        foreach ($forcedRules as $ruleKey) {
            if (array_key_exists($ruleKey, $storedRules)) {
                $rules[$ruleKey] = $storedRules[$ruleKey];
            } else {
                unset($rules[$ruleKey]);
            }
        }

        if ($rules !== []) {
            $metadata['rules'] = $rules;
        } else {
            unset($metadata['rules']);
        }

        if (array_key_exists('du_option_map', $metadata)) {
            $metadata['du_option_map'] = $this->normalizeOptionMap($metadata['du_option_map'], $stored);
        }

        $row['type'] = FormFieldTypeMapping::toApiType((string) $stored->input_type);
        $row['name'] = (string) $stored->name;
        $row['metadata'] = $metadata;

        return $row;
    }

    /**
     * Keeps only mapping entries whose option label still exists and whose course is cached,
     * any active state: the mapping endpoint requires an active course, while issuance accepts
     * an inactive cached course (DU is authoritative), so autosave must not drop historical mappings.
     *
     * @return array<string, array{course_uid: string, course_title: string}>
     */
    private function normalizeOptionMap(mixed $mapping, FormField $stored): array
    {
        if ($mapping instanceof Collection) {
            $mapping = $mapping->all();
        }

        if (! is_array($mapping) || $mapping === []) {
            return [];
        }

        $labels = $this->optionLabels($stored);
        $uids = [];
        foreach ($mapping as $entry) {
            if (is_array($entry) && is_string($entry['course_uid'] ?? null)) {
                $uids[] = $entry['course_uid'];
            }
        }

        $titles = $uids === []
            ? new Collection([])
            : DuCourse::query()->whereIn('uid', array_values(array_unique($uids)))->pluck('title', 'uid');

        $normalized = [];
        foreach ($mapping as $optionValue => $entry) {
            $value = is_int($optionValue) ? (string) $optionValue : $optionValue;

            if (! is_string($value) || ! is_array($entry)) {
                continue;
            }

            $courseUid = $entry['course_uid'] ?? null;
            if (! is_string($courseUid) || ! in_array($value, $labels, true) || ! $titles->has($courseUid)) {
                continue;
            }

            $normalized[$value] = [
                'course_uid' => $courseUid,
                'course_title' => (string) $titles->get($courseUid),
            ];
        }

        return $normalized;
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

    /**
     * @return list<string>
     */
    private function labelsFromChoices(mixed $choices): array
    {
        if ($choices instanceof Collection) {
            $choices = $choices->all();
        }

        if (is_string($choices)) {
            $decoded = json_decode($choices, true);

            return is_array($decoded) ? $this->labelsFromChoices($decoded) : $this->labelsFromCsv($choices);
        }

        if (! is_array($choices)) {
            return [];
        }

        $labels = [];
        foreach ($choices as $choice) {
            $label = is_array($choice) ? ($choice['label'] ?? null) : $choice;

            if ((is_string($label) || is_int($label)) && trim((string) $label) !== '') {
                $labels[] = trim((string) $label);
            }
        }

        return $labels;
    }

    /** @return list<string> */
    private function labelsFromCsv(mixed $csv): array
    {
        if (! is_string($csv) || trim($csv) === '') {
            return [];
        }

        $labels = [];
        foreach (explode(',', $csv) as $label) {
            $label = trim($label);

            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * The system field repeats per invited member only for D-Kelas team forms; bundle uses
     * `metadata.duplicatable`, single mode keeps one value.
     */
    private function isAppendable(Form $form): bool
    {
        if ($form->purpose() !== FormPurpose::DKelas) {
            return false;
        }

        $metadata = is_array($form->metadata) ? $form->metadata : [];
        $mode = $metadata['registration_mode'] ?? null;

        return is_string($mode) && strtolower($mode) === 'team';
    }

    /** A snapshot row claims the system field when it carries its id, its name, or its system metadata. */
    private function claimsSystemIdentity(array $row, ?string $systemId): bool
    {
        $id = $row['id'] ?? null;
        if ($systemId !== null && is_string($id) && $id === $systemId) {
            return true;
        }

        if (($row['name'] ?? null) === self::FIELD_NAME) {
            return true;
        }

        $metadata = $row['metadata'] ?? null;

        return is_array($metadata)
            && (($metadata['system'] ?? false) === true || ($metadata['options_source'] ?? null) === 'du_catalog');
    }

    /**
     * Keeps the row that owns the stored field id so the snapshot upserts it instead of creating a copy.
     *
     * @param  list<array<string, mixed>>  $claimed
     */
    private function preferredSystemRow(array $claimed, ?string $systemId): array
    {
        foreach ($claimed as $row) {
            if ($systemId !== null && ($row['id'] ?? null) === $systemId) {
                return $row;
            }
        }

        return $claimed[0];
    }

    /**
     * @param  array<string, mixed>  $clientRow
     * @return array<string, mixed>
     */
    private function systemFieldRow(?FormField $existing, array $clientRow, bool $appendable): array
    {
        $metadata = $clientRow['metadata'] ?? [];
        $metadata = is_array($metadata) ? $metadata : [];
        // Opsi selalu dibaca dari cache katalog, tidak pernah disimpan di metadata.
        unset($metadata['optionChoices'], $metadata['options']);

        $label = $clientRow['label'] ?? null;
        if (! is_string($label) || trim($label) === '') {
            $label = $existing?->label ?? 'Kelas';
        }

        $description = $clientRow['description'] ?? null;
        if (! array_key_exists('description', $clientRow) || (! is_string($description) && $description !== null)) {
            $description = $existing?->description;
        }

        $order = $clientRow['order'] ?? null;
        $order = is_numeric($order) ? (int) $order : (int) ($existing?->order ?? 0);

        $row = [
            'name' => self::FIELD_NAME,
            'type' => 'select',
            'label' => $label,
            'description' => $description,
            'order' => $order,
            'metadata' => array_replace_recursive($metadata, self::SYSTEM_METADATA),
            'is_append' => $appendable,
        ];

        $id = $existing?->id ?? ($clientRow['id'] ?? null);
        if (is_string($id) && $id !== '') {
            $row['id'] = $id;
        }

        return $row;
    }
}

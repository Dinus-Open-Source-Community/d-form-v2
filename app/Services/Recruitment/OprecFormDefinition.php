<?php

namespace App\Services\Recruitment;

use App\Models\Form;
use App\Models\FormField;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Support\FormFieldTypeMapping;
use Illuminate\Support\Collection;

class OprecFormDefinition
{
    public const FORM_TITLE = 'Formulir Open Recruitment';

    public function __construct(private readonly RecruitmentDivisionService $divisionService) {}

    /**
     * Future-compatible: utamakan form yang terhubung ke period bila kolom itu
     * tersedia, fallback ke form bertanda metadata.oprec.
     */
    public function requiredForm(?RecruitmentPeriod $period = null): Form
    {
        $formId = $period?->form_id;

        if (is_string($formId) && $formId !== '') {
            $linked = Form::query()->find($formId);
            if ($linked !== null) {
                return $linked;
            }
        }

        $form = Form::query()
            ->oprec()
            ->where('title', self::FORM_TITLE)
            ->first();
        abort_if($form === null, 503, 'Formulir pendaftaran sedang dalam pemeliharaan. Coba lagi nanti.');

        return $form;
    }

    /** @return Collection<int, FormField> */
    public function orderedFields(): Collection
    {
        return $this->requiredForm()->formFields()->orderBy('order')->get();
    }

    /** @return array<string, string> division name => id, active only */
    public function activeDivisionMap(): array
    {
        return $this->divisionService->listActiveOrdered()
            ->mapWithKeys(fn ($division) => [$division->name => $division->id])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function fieldsForDisplay(): array
    {
        $form = $this->requiredForm();
        $form->load(['formFields' => fn ($query) => $query->orderBy('order')]);
        $divisionNames = array_keys($this->activeDivisionMap());

        return $form->formFields
            ->map(function (FormField $field) use ($divisionNames) {
                $row = FormFieldTypeMapping::fieldToInertia($field);
                if (in_array($field->name, ['primary_division_id', 'secondary_division_id'], true)) {
                    $row['metadata']['options'] = $divisionNames;
                }

                return $row;
            })
            ->values()
            ->all();
    }
}

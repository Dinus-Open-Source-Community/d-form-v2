<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Form\RulesBuilder;
use App\Services\Recruitment\OprecFormDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OprecFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $fields = app(OprecFormDefinition::class)->orderedFields();

        return RulesBuilder::build(RulesBuilder::extractRulesFromFields($fields));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $period = $this->attributes->get('recruitment_period');

            if ($period === null) {
                $validator->errors()->add('period', 'Periode pendaftaran tidak tersedia.');

                return;
            }

            $divisions = app(OprecFormDefinition::class)->activeDivisionMap();
            $primaryName = (string) $this->input('primary_division_id');

            if (! isset($divisions[$primaryName])) {
                $validator->errors()->add('primary_division_id', 'Divisi utama tidak valid.');
            }

            $secondaryName = $this->input('secondary_division_id');
            $secondaryId = null;

            if ($secondaryName !== null && $secondaryName !== '') {
                if (! isset($divisions[(string) $secondaryName])) {
                    $validator->errors()->add('secondary_division_id', 'Divisi cadangan tidak valid.');
                } else {
                    $secondaryId = $divisions[(string) $secondaryName];
                }
            }

            if (isset($divisions[$primaryName]) && $secondaryId !== null && $secondaryId === $divisions[$primaryName]) {
                $validator->errors()->add('secondary_division_id', 'Divisi cadangan tidak boleh sama dengan divisi utama.');
            }

            if ($this->input('portfolio_type') === 'url' && trim((string) $this->input('portfolio_url')) === '') {
                $validator->errors()->add('portfolio_url', 'Link portfolio wajib diisi.');
            }

            if ($this->input('portfolio_type') === 'file' && $this->file('portfolio_file') === null) {
                $validator->errors()->add('portfolio_file', 'File portfolio wajib diunggah.');
            }

            $nim = (string) $this->input('nim');

            if ($nim !== '' && RecruitmentApplication::query()
                ->where('recruitment_period_id', $period->id)
                ->where('nim', $nim)
                ->exists()) {
                $validator->errors()->add('nim', 'NIM ini sudah terdaftar pada periode ini.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function validatedPayload(): array
    {
        $validated = $this->validated();
        $divisions = app(OprecFormDefinition::class)->activeDivisionMap();
        $validated['primary_division_id'] = $divisions[(string) $validated['primary_division_id']] ?? $validated['primary_division_id'];

        if (! empty($validated['secondary_division_id'])) {
            $validated['secondary_division_id'] = $divisions[(string) $validated['secondary_division_id']] ?? $validated['secondary_division_id'];
        }

        $validated['instagram_username'] = ltrim((string) $validated['instagram_username'], '@');

        return $validated;
    }
}

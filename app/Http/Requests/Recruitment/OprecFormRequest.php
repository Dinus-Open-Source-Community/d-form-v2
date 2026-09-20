<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Form\RulesBuilder;
use App\Services\Recruitment\OprecFormDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OprecFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        try {
            $fields = app(OprecFormDefinition::class)->orderedFields();
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 503) {
                throw $e;
            }

            return self::legacyRules();
        }

        return RulesBuilder::build(RulesBuilder::extractRulesFromFields($fields));
    }

    /**
     * Old-contract rules used only when the DB-driven oprec form is not
     * seeded. Mirrors StoreApplicationRequest::rules() so UUID-based
     * submissions keep validating without touching legacy callers.
     *
     * @return array<string, mixed>
     */
    private static function legacyRules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'nim' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_.]+$/'],
            'semester' => ['required', 'integer', Rule::in([1, 3])],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9]{10,15}$/'],
            'personal_email' => ['required', 'email', 'max:255'],
            'student_email' => ['required', 'email', 'max:255'],
            'instagram_username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.]+$/'],
            'primary_division_id' => [
                'required',
                'uuid',
                Rule::exists('recruitment_divisions', 'id')->where('is_active', true),
            ],
            'secondary_division_id' => [
                'nullable',
                'uuid',
                'different:primary_division_id',
                Rule::exists('recruitment_divisions', 'id')->where('is_active', true),
            ],
            'portfolio_type' => ['required', Rule::in(['url', 'file'])],
            'portfolio_url' => ['nullable', 'required_if:portfolio_type,url', 'url', 'max:500'],
            'portfolio_file' => ['nullable', 'required_if:portfolio_type,file', 'file', 'mimes:pdf', 'max:5120'],
            'cv' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ];
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
            $resolveId = static function (mixed $value) use ($divisions): ?string {
                $key = (string) $value;

                if (isset($divisions[$key])) {
                    return $divisions[$key];
                }

                if (in_array($key, $divisions, true)) {
                    return $key;
                }

                return null;
            };
            $primaryId = $resolveId($this->input('primary_division_id'));

            if ($primaryId === null) {
                $validator->errors()->add('primary_division_id', 'Divisi utama tidak valid.');
            }

            $secondaryName = $this->input('secondary_division_id');
            $secondaryId = null;

            if ($secondaryName !== null && $secondaryName !== '') {
                $secondaryId = $resolveId($secondaryName);

                if ($secondaryId === null) {
                    $validator->errors()->add('secondary_division_id', 'Divisi cadangan tidak valid.');
                }
            }

            if ($primaryId !== null && $secondaryId !== null && $secondaryId === $primaryId) {
                $validator->errors()->add('secondary_division_id', 'Divisi cadangan tidak boleh sama dengan divisi utama.');
            }

            if ($this->input('portfolio_type') === 'url' && trim((string) $this->input('portfolio_url')) === '') {
                $validator->errors()->add('portfolio_url', 'Link portfolio wajib diisi.');
            }

            if ($this->input('portfolio_type') === 'file' && $this->file('portfolio_file') === null) {
                $validator->errors()->add('portfolio_file', 'File portfolio wajib diunggah.');
            }

            $nim = Str::upper(trim((string) $this->input('nim')));

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

        $validated['nim'] = Str::upper(trim((string) $validated['nim']));
        $validated['instagram_username'] = ltrim((string) $validated['instagram_username'], '@');

        return $validated;
    }
}

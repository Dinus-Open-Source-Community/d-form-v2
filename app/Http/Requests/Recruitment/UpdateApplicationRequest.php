<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RecruitmentApplication|null $application */
        $application = $this->attributes->get('recruitment_application');

        return $application !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var RecruitmentApplication|null $application */
        $application = $this->attributes->get('recruitment_application');
        $hasCv = $application?->document?->cv_path !== null;

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'nim' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_.]+$/'],
            'semester' => ['required', 'integer', 'between:1,3'],
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
            'cv' => [$hasCv ? 'nullable' : 'required', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var RecruitmentApplication|null $application */
            $application = $this->attributes->get('recruitment_application');

            if ($application === null) {
                return;
            }

            $nim = (string) $this->input('nim');

            if (RecruitmentApplication::query()
                ->where('recruitment_period_id', $application->recruitment_period_id)
                ->where('nim', $nim)
                ->where('id', '!=', $application->id)
                ->exists()) {
                $validator->errors()->add('nim', 'NIM ini sudah terdaftar pada periode ini.');
            }

            if ($this->input('portfolio_type') === 'file'
                && ! $this->hasFile('portfolio_file')
                && blank($application->document?->portfolio_path)) {
                $validator->errors()->add('portfolio_file', 'Portfolio file wajib diunggah.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();
        $validated['instagram_username'] = ltrim((string) $validated['instagram_username'], '@');

        return $validated;
    }
}

<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
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
            'instagram_follow_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'twibbon_url' => ['required', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nim.regex' => 'Format NIM tidak valid.',
            'semester.in' => 'Semester hanya boleh 1 atau 3.',
            'phone.regex' => 'Nomor telepon harus 10–15 digit.',
            'secondary_division_id.different' => 'Divisi cadangan tidak boleh sama dengan divisi utama.',
            'cv.mimes' => 'CV harus berformat PDF.',
            'portfolio_file.mimes' => 'Portfolio file harus berformat PDF.',
            'instagram_follow_proof.mimes' => 'Bukti follow Instagram harus berformat gambar (jpg, jpeg, png, atau webp).',
            'twibbon_url.required' => 'Link bukti twibbon wajib diisi.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var RecruitmentPeriod|null $period */
            $period = $this->attributes->get('recruitment_period');

            if ($period === null) {
                $validator->errors()->add('period', 'Periode pendaftaran tidak tersedia.');

                return;
            }

            $nim = (string) $this->input('nim');

            if (RecruitmentApplication::query()
                ->where('recruitment_period_id', $period->id)
                ->where('nim', $nim)
                ->exists()) {
                $validator->errors()->add('nim', 'NIM ini sudah terdaftar pada periode ini.');
            }

            $primaryId = (string) $this->input('primary_division_id');
            $secondaryId = $this->input('secondary_division_id');

            if ($secondaryId !== null && (string) $secondaryId === $primaryId) {
                $validator->errors()->add('secondary_division_id', 'Divisi cadangan tidak boleh sama dengan divisi utama.');
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

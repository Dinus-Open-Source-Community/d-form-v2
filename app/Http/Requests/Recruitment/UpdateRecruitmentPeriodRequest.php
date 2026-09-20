<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecruitmentPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.periods.edit') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at'],
            'interview_starts_at' => ['nullable', 'date'],
            'interview_ends_at' => ['nullable', 'date', 'after_or_equal:interview_starts_at'],
            'finalization_deadline_at' => ['nullable', 'date'],
            'banner' => ['sometimes', 'nullable', 'image', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'banner.image' => 'Banner harus berupa file gambar.',
            'banner.max' => 'Ukuran banner tidak boleh lebih dari 10 MB.',
            'banner.mimes' => 'Banner harus berformat JPG, JPEG, PNG, atau WEBP.',
        ];
    }
}

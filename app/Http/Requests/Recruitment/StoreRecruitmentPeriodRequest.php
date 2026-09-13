<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecruitmentPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.periods.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at'],
            'interview_starts_at' => ['nullable', 'date'],
            'interview_ends_at' => ['nullable', 'date', 'after_or_equal:interview_starts_at'],
            'finalization_deadline_at' => ['nullable', 'date'],
        ];
    }
}

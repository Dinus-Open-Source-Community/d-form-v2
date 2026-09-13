<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecruitmentInterviewSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.interviews.schedule') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recruitment_period_id' => ['required', 'uuid', Rule::exists('recruitment_periods', 'id')],
            'recruitment_division_id' => [
                'required',
                'uuid',
                Rule::exists('recruitment_divisions', 'id')->where('is_active', true),
            ],
            'session_date' => ['required', 'date', 'after_or_equal:today'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'location' => ['required', 'string', 'max:255'],
            'room' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

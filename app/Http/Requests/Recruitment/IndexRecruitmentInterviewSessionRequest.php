<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRecruitmentInterviewSessionRequest extends FormRequest
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
            'period_id' => ['nullable', 'uuid'],
            'division_id' => ['nullable', 'uuid'],
            'is_active' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

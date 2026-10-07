<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClaimSecondaryInterviewRequest extends FormRequest
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
            'application_id' => ['required', 'uuid', Rule::exists('recruitment_applications', 'id')],
            'session_id' => ['required', 'uuid', Rule::exists('recruitment_interview_sessions', 'id')],
        ];
    }
}

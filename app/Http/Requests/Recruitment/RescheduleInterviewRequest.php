<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RescheduleInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.interviews.reschedule') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recruitment_interview_session_id' => [
                'required',
                'uuid',
                Rule::exists('recruitment_interview_sessions', 'id'),
            ],
        ];
    }
}

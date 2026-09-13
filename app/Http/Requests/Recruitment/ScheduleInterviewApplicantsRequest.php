<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleInterviewApplicantsRequest extends FormRequest
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
            'application_ids' => ['required', 'array', 'min:1'],
            'application_ids.*' => ['uuid'],
        ];
    }
}

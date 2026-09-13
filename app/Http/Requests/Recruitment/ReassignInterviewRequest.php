<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.interviews.reassign') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'interviewer_id' => ['required', 'uuid', Rule::exists('users', 'id')],
        ];
    }
}

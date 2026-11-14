<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi ditegakkan di policy assignInterview via controller.
        return true;
    }

    /**
     *
     * @return array<string, mixed>
     *
     */
    public function rules(): array
    {
        return [
            'interviewer_id' => ['required', 'uuid', Rule::exists('users', 'id')],
        ];
    }
}

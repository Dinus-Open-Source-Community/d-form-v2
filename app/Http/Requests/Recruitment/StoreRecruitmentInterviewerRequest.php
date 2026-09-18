<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecruitmentInterviewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.interviewers.assign') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'min:8', 'confirmed'],
            'recruitment_division_id' => ['required', 'uuid', Rule::exists('recruitment_divisions', 'id')],
        ];
    }
}

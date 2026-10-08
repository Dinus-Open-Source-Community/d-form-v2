<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecruitmentInterviewSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.interviews.schedule') ?? false;
    }

    /**
     * recruitment_period_id diabaikan saat update (tidak boleh pindah periode);
     * `exclude` mengeluarkannya dari validated data sehingga tidak pernah
     * sampai ke service walaupun ikut terkirim oleh client.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recruitment_period_id' => ['exclude'],
            'recruitment_division_id' => [
                'required',
                'uuid',
                Rule::exists('recruitment_divisions', 'id')->where('is_active', true),
            ],
            'session_date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'location' => ['required', 'string', 'max:255'],
            'room' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

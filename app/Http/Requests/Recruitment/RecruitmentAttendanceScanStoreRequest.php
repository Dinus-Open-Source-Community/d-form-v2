<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Recruitment\RecruitmentInterviewSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RecruitmentAttendanceScanStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.attendance.scan') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'uuid', 'exists:recruitment_interview_sessions,id'],
            'registration_number' => ['nullable', 'string', 'max:32'],
            'application_id' => ['nullable', 'string', 'uuid'],
            'raw_payload' => ['nullable', 'string', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasNumber = filled(trim((string) $this->input('registration_number', '')));
            $hasApplicationId = filled(trim((string) $this->input('application_id', '')));
            $hasRaw = filled(trim((string) $this->input('raw_payload', '')));

            if (! $hasNumber && ! $hasApplicationId && ! $hasRaw) {
                $validator->errors()->add(
                    'payload',
                    __('Provide a QR payload, registration number, or application ID.'),
                );
            }
        });
    }

    public function session(): RecruitmentInterviewSession
    {
        return RecruitmentInterviewSession::query()->findOrFail($this->validated('session_id'));
    }
}

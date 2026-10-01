<?php

namespace App\Http\Requests\Recruitment;

use App\Services\Recruitment\RecruitmentEmailResendService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResendRecruitmentEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application !== null
            && $this->user()?->can('resendTrackingInformation', $application);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(RecruitmentEmailResendService::TYPES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Jenis resend tidak valid — pilih tracking, confirmation, correction, interviewer, atau notification.',
        ];
    }
}

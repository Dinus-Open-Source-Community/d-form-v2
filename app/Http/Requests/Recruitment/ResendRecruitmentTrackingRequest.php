<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class ResendRecruitmentTrackingRequest extends FormRequest
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
        return [];
    }
}

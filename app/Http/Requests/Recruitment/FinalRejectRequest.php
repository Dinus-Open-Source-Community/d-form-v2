<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class FinalRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application !== null
            && $this->user()?->can('decideFinal', $application);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'internal_reason' => ['required', 'string', 'max:2000'],
            'public_message' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'internal_reason.required' => 'Alasan internal wajib diisi.',
            'public_message.required' => 'Pesan untuk applicant wajib diisi.',
        ];
    }
}

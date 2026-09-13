<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorrectionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('recruitment_application') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'request_message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'request_message.required' => 'Pesan permintaan koreksi wajib diisi.',
            'request_message.min' => 'Pesan permintaan koreksi minimal 10 karakter.',
        ];
    }
}

<?php

namespace App\Http\Requests\Recruitment;

use App\Enums\Recruitment\ScreeningReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScreeningRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application !== null
            && $this->user()?->can('screen', $application);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ScreeningReason::class)],
            'notes' => [
                Rule::requiredIf(fn (): bool => $this->input('reason') === ScreeningReason::Other->value),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan revisi wajib diisi.',
            'notes.required' => 'Catatan wajib diisi jika alasan "Lainnya".',
        ];
    }
}

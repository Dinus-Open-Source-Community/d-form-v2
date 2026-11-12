<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Kirim QR bulk ke applicant tahap interview; otorisasi `sendQr` pada periode
 * dipertahankan dari controller.
 */
class SendPeriodQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sendQr', $this->route('period')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'include_sent' => ['sometimes', 'boolean'],
        ];
    }
}

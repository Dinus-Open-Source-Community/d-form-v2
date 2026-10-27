<?php

namespace App\Http\Requests\Broadcast;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Kirim email uji satu alamat; otorisasi `view` pada broadcast yang sama
 * dengan yang dicek controller sebelumnya.
 */
class SendBroadcastTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('broadcast')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ];
    }
}

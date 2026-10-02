<?php

namespace App\Http\Requests\Broadcasting;

use Illuminate\Foundation\Http\FormRequest;

class StoreBroadcastRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('email-broadcast.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }
}

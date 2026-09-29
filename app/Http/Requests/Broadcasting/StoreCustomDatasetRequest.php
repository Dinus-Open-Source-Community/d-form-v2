<?php

namespace App\Http\Requests\Broadcasting;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('email-broadcast.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'manual' => ['sometimes', 'array', 'max:1000'],
            'manual.*.name' => ['nullable', 'string', 'max:150'],
            'manual.*.email' => ['required_with:manual', 'email:rfc', 'max:255'],
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}

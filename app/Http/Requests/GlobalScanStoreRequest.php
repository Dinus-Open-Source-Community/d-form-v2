<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GlobalScanStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->can('events.list') || $user->can('recruitment.attendance.scan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'raw' => ['required', 'string', 'max:4096'],
            'desk' => ['nullable', 'string', 'max:32'],
        ];
    }
}

<?php

namespace App\Http\Requests\Broadcasting;

use Illuminate\Foundation\Http\FormRequest;

class SaveBroadcastContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('email-broadcast.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:500000'],
            'event_id' => ['nullable', 'uuid', 'exists:events,id'],
        ];
    }
}

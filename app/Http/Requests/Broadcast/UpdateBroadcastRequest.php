<?php

namespace App\Http\Requests\Broadcast;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Konten boleh diubah; scope + snapshot tidak tersentuh update. */
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'send_delay_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ];
    }
}

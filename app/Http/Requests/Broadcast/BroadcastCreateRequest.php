<?php

namespace App\Http\Requests\Broadcast;

use Illuminate\Foundation\Http\FormRequest;

class BroadcastCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Kontrak query hub: event_id / period_id opsional, wajib UUID yang terdaftar. */
    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'uuid', 'exists:events,id'],
            'period_id' => ['nullable', 'uuid', 'exists:recruitment_periods,id'],
        ];
    }

    /** Pesan jelas saat query konteks tidak terdaftar. */
    public function messages(): array
    {
        return [
            'event_id.exists' => 'Event tidak ditemukan — periksa kembali event_id.',
            'period_id.exists' => 'Periode tidak ditemukan — periksa kembali period_id.',
        ];
    }
}

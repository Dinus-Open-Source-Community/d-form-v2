<?php

namespace App\Http\Requests\Broadcast;

use App\Models\Broadcast;
use Illuminate\Foundation\Http\FormRequest;

class StoreBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Sumber event_participants wajib event_id; recruitment_applicants wajib period_id. */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'source' => ['required', 'in:'.implode(',', Broadcast::SOURCES)],
            'event_id' => ['required_if:source,'.Broadcast::SOURCE_EVENT_PARTICIPANTS, 'nullable', 'uuid', 'exists:events,id'],
            'period_id' => ['required_if:source,'.Broadcast::SOURCE_RECRUITMENT_APPLICANTS, 'nullable', 'uuid', 'exists:recruitment_periods,id'],
            'scheduled_at' => ['nullable', 'date'],
            'send_delay_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ];
    }

    /** Pesan jelas: tolak kosong, jangan jatuhkan ke semua-data. */
    public function messages(): array
    {
        return [
            'source.in' => 'Sumber tidak valid — pilih event_participants atau recruitment_applicants.',
            'event_id.required_if' => 'Pilih event dulu — sumber peserta event wajib menyertakan event_id.',
            'period_id.required_if' => 'Pilih periode dulu — sumber pelamar wajib menyertakan period_id.',
            'event_id.exists' => 'Event tidak ditemukan — periksa kembali event_id.',
            'period_id.exists' => 'Periode tidak ditemukan — periksa kembali period_id.',
        ];
    }
}

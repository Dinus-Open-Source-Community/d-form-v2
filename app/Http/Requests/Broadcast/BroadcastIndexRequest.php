<?php

namespace App\Http\Requests\Broadcast;

use Illuminate\Foundation\Http\FormRequest;

class BroadcastIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** List broadcast wajib terfilter satu periode (cegah list global bocor). */
    public function rules(): array
    {
        return [
            'period_id' => ['required', 'uuid', 'exists:recruitment_periods,id'],
        ];
    }

    /** Pesan jelas saat filter periode absen. */
    public function messages(): array
    {
        return [
            'period_id.required' => 'Pilih periode dulu — daftar broadcast wajib menyertakan period_id.',
            'period_id.exists' => 'Periode tidak ditemukan — periksa kembali period_id.',
        ];
    }
}

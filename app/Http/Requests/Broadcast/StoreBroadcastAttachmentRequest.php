<?php

namespace App\Http\Requests\Broadcast;

use Illuminate\Foundation\Http\FormRequest;

class StoreBroadcastAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Satu file pdf/jpg/png ≤5MB; total maks 3 dicek di controller. */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /** Pesan jelas untuk format/ukuran lampiran yang ditolak. */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file dulu — lampiran wajib menyertakan file.',
            'file.mimes' => 'Format tak didukung — gunakan pdf, jpg, atau png.',
            'file.max' => 'File kebesaran — maksimal 5MB.',
        ];
    }
}

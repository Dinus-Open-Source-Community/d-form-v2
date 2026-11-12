<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi kode OTP halaman verifikasi; email sengaja tidak diterima dari body.
 */
class VerifyOtpRequest extends FormRequest
{
    /** Endpoint ini terbuka untuk guest. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kode wajib tepat 6 digit angka.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => 'required|digits:6',
        ];
    }

    /**
     * Pesan validasi dalam bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Masukkan kode verifikasi.',
            'code.digits' => 'Kode verifikasi harus 6 digit angka.',
        ];
    }
}

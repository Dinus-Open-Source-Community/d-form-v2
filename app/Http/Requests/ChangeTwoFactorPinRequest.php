<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmsTwoFactorCredentials;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Ubah PIN faktor kedua: wajib PIN saat ini, plus kata sandi bila akun punya kata sandi lokal. */
class ChangeTwoFactorPinRequest extends FormRequest
{
    use ConfirmsTwoFactorCredentials;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     *
     */
    public function rules(): array
    {
        $rules = [
            'pin' => $this->pinRules(),
            'current_pin' => $this->pinRules(),
        ];

        if ($this->hasLocalPassword()) {
            $rules['current_password'] = $this->currentPasswordRules();
        }

        return $rules;
    }

    /**
     *
     * @return array<string, string>
     *
     */
    public function messages(): array
    {
        $length = max(1, (int) config('otp.length'));

        return [
            ...$this->pinMessages(),
            'current_pin.required' => 'Masukkan PIN yang sekarang.',
            'current_pin.string' => 'PIN saat ini harus berupa angka.',
            'current_pin.digits' => 'PIN saat ini harus terdiri dari '.$length.' angka.',
            'current_password.required' => 'Masukkan kata sandi Anda untuk mengubah PIN.',
        ];
    }
}

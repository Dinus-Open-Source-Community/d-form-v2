<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmsTwoFactorCredentials;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Aktifkan PIN faktor kedua; akun dengan kata sandi lokal wajib re-auth (spec §6.5). */
class EnableTwoFactorRequest extends FormRequest
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
        $rules = ['pin' => $this->pinRules()];

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
        return [
            ...$this->pinMessages(),
            'current_password.required' => 'Masukkan kata sandi Anda untuk mengaktifkan PIN.',
        ];
    }
}

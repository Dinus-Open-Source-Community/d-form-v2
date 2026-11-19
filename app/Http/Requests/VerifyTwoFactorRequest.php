<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmsTwoFactorCredentials;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Verifikasi PIN pada halaman tantangan faktor kedua (spec §6.3). */
class VerifyTwoFactorRequest extends FormRequest
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
        return ['pin' => $this->pinRules()];
    }

    /**
     *
     * @return array<string, string>
     *
     */
    public function messages(): array
    {
        return $this->pinMessages();
    }
}

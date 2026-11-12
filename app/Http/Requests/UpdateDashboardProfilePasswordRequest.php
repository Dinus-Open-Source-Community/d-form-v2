<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UpdateDashboardProfilePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = $this->user();
        $hasLocalPassword = (bool) ($user->has_local_password ?? filled($user->getRawOriginal('password')));

        $passwordRule = ['required', 'string', 'confirmed', Password::min(8)];

        $rules = [
            'password' => $passwordRule,
            'password_confirmation' => ['required', 'string'],
        ];

        if ($hasLocalPassword) {
            $rules['current_password'] = [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    /** @var \App\Models\User $user */
                    $user = $this->user();
                    // Password tidak lagi ikut di query sesi; ambil hash khusus untuk aksi ubah password.
                    $hash = $user->newQuery()->whereKey($user->getKey())->value('password');
                    if (! is_string($hash) || ! is_string($value) || ! Hash::check($value, $hash)) {
                        $fail(__('validation.current_password'));
                    }
                },
            ];
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Please enter your current password to confirm this change.',
            'current_password.string' => 'Current password must be valid text.',
            'password.required' => 'Please enter your new password.',
            'password.string' => 'Password must be valid text.',
            'password.confirmed' => 'The new password confirmation does not match. Please re-enter your password.',
            'password_confirmation.required' => 'Please confirm your new password.',
            'password_confirmation.string' => 'Password confirmation must be valid text.',
        ];
    }
}

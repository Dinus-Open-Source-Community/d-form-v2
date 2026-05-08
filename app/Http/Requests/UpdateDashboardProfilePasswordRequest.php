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
        $hasLocalPassword = filled($user->getRawOriginal('password'));

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
                    $hash = $user->getRawOriginal('password');
                    if (! is_string($hash) || ! is_string($value) || ! Hash::check($value, $hash)) {
                        $fail(__('validation.current_password'));
                    }
                },
            ];
        }

        return $rules;
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'token' => $this->route('token'),
        ]);
    }

    /**
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     *
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     *
     *
     * @return array<string, string>
     *
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Password reset token is missing. Please use the link from your email.',
            'token.string' => 'Invalid password reset token format.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address format (e.g., user@example.com).',
            'password.required' => 'Please enter your new password.',
            'password.string' => 'Password must be a valid text.',
            'password.min' => 'Your new password must be at least 8 characters long.',
            'password.confirmed' => 'The password confirmation does not match. Please re-enter your password.',
            'password_confirmation.required' => 'Please confirm your new password.',
            'password_confirmation.string' => 'Password confirmation must be a valid text.',
        ];
    }
}

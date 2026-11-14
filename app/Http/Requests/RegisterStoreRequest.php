<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     *
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     *
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'email' => "required|email|unique:users,email",
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required'
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
            'name.required' => 'Please enter your full name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address format (e.g., user@example.com).',
            'email.unique' => 'This email address is already registered. Please use a different email or try logging in.',
            'password.required' => 'Please enter a password.',
            'password.min' => 'Your password must be at least 8 characters long.',
            'password.confirmed' => 'The password confirmation does not match. Please re-enter your password.',
            'password_confirmation.required' => 'Please confirm your password.',
        ];
    }
}

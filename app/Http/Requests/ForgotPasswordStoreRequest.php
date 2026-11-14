<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     *
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
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
            'email.required' => 'Please enter your email address to reset your password.',
            'email.email' => 'Please enter a valid email address format (e.g., user@example.com).',
        ];
    }
}

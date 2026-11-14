<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginStoreRequest extends FormRequest
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
            'email' => 'required|email',
            'password' => 'required'
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
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address format (e.g., user@example.com).',
            'password.required' => 'Please enter your password.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     *
     */
    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($user->getKey())
                    ->whereNull('deleted_at'),
            ],
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
            'name.required' => 'Please enter your name.',
            'name.string' => 'Your name must be valid text.',
            'name.max' => 'Your name cannot be longer than 150 characters.',
            'email.required' => 'Please enter your email address.',
            'email.string' => 'Email address must be valid text.',
            'email.lowercase' => 'Email address must be in lowercase.',
            'email.email' => 'Please enter a valid email address format (e.g., user@example.com).',
            'email.max' => 'Email address cannot be longer than 255 characters.',
            'email.unique' => 'This email address is already in use by another account. Please use a different email.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => strtolower($this->input('email')),
            ]);
        }
    }
}

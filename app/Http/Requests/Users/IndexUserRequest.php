<?php

namespace App\Http\Requests\Users;

use App\Services\User\UserManagementService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.list') ?? false;
    }

    /**
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     *
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'nullable', 'string', Rule::in(UserManagementService::ASSIGNABLE_ROLES)],
            'page' => ['sometimes', 'integer', 'min:1'],
            // Whitelist per_page ditegakkan service; rule ini hanya menolak nilai non-numerik.
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'edit' => ['sometimes', 'nullable', 'uuid'],
            'create' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim((string) $this->input('search', '')),
            'role' => $this->filled('role') ? (string) $this->input('role') : null,
            'per_page' => (int) $this->input('per_page', UserManagementService::DEFAULT_PER_PAGE),
        ]);
    }
}

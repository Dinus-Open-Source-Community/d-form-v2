<?php

namespace App\Http\Requests\Broadcasting;

use App\Enums\EmailDatasetSourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('email-broadcast.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'datasets' => ['sometimes', 'array', 'max:20'],
            'datasets.*.type' => ['required_with:datasets', 'string', Rule::in(EmailDatasetSourceType::values())],
            'datasets.*.id' => ['nullable', 'string', 'max:100'],
            'datasets.*.event_id' => ['nullable', 'uuid', 'exists:events,id'],
            'datasets.*.period_id' => ['nullable', 'uuid', 'exists:recruitment_periods,id'],
            'manual' => ['sometimes', 'array', 'max:500'],
            'manual.*.name' => ['nullable', 'string', 'max:150'],
            'manual.*.email' => ['required_with:manual', 'email:rfc', 'max:255'],
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Enums\EventStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('events.list');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter' => ['sometimes', 'array'],
            'filter.categories' => ['sometimes', 'array'],
            'filter.categories.*' => [Rule::enum(EventCategory::class)],
            'filter.sessions' => ['sometimes', 'array'],
            'filter.sessions.*' => [Rule::enum(EventSession::class)],
            'filter.statuses' => ['sometimes', 'array'],
            'filter.statuses.*' => [Rule::enum(EventStatus::class)],
            'filter.showTrashed' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'array'],
            'sort.by' => ['sometimes', 'string', Rule::in(['title', 'price', 'end_date'])],
            'sort.order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $filter = $this->input('filter', []);

        $this->merge([
            'search' => trim((string) $this->input('search', '')),
            'per_page' => (int) $this->input('per_page', 10),
            'filter' => [
                'categories' => $filter['categories'] ?? [],
                'sessions' => $filter['sessions'] ?? [],
                'statuses' => $filter['statuses'] ?? [],
                'showTrashed' => filter_var(
                    $filter['showTrashed'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
            ],
            'sort' => array_merge(
                [
                    'by' => 'title',
                    'order' => 'asc',
                ],
                $this->input('sort', [])
            ),
        ]);
    }
}

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
            'filter.timeline' => ['sometimes', 'string', Rule::in(['all', 'upcoming', 'ongoing', 'completed'])],
            'sort' => ['sometimes', 'array'],
            'sort.by' => ['sometimes', 'string', Rule::in(['title', 'price', 'end_date'])],
            'sort.order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.string' => 'Search query must be valid text.',
            'search.max' => 'Search query cannot be longer than 255 characters.',
            'page.integer' => 'Page number must be a whole number.',
            'page.min' => 'Page number must be at least 1.',
            'per_page.integer' => 'Items per page must be a whole number.',
            'per_page.min' => 'Items per page must be at least 1.',
            'per_page.max' => 'Items per page cannot exceed 100.',
            'filter.array' => 'Filters must be a valid list.',
            'filter.categories.array' => 'Category filters must be a valid list.',
            'filter.sessions.array' => 'Session filters must be a valid list.',
            'filter.statuses.array' => 'Status filters must be a valid list.',
            'filter.showTrashed.boolean' => 'Show deleted events must be true or false.',
            'filter.timeline.string' => 'Timeline filter must be valid text.',
            'filter.timeline.in' => 'Timeline filter must be one of: all, upcoming, ongoing, or completed.',
            'sort.array' => 'Sort options must be a valid list.',
            'sort.by.string' => 'Sort field must be valid text.',
            'sort.by.in' => 'Sort field must be one of: title, price, or end_date.',
            'sort.order.string' => 'Sort order must be valid text.',
            'sort.order.in' => 'Sort order must be either asc or desc.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $filter = $this->input('filter', []);
        $filter = is_array($filter) ? $filter : [];

        $sort = $this->input('sort', []);
        $sort = is_array($sort) ? $sort : [];

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
                'timeline' => $filter['timeline'] ?? 'all',
            ],
            'sort' => array_merge(
                [
                    'by' => 'title',
                    'order' => 'asc',
                ],
                $sort
            ),
        ]);
    }
}

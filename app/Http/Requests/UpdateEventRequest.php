<?php

namespace App\Http\Requests;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Services\Event\EventService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('events.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'registration_start' => ['required', 'date', 'before_or_equal:start_date'],
            'registration_end' => ['required', 'date', 'after:registration_start', 'before_or_equal:end_date'],
            'start_date' => ['required', 'date', 'after_or_equal:registration_start'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'quota' => ['required', 'integer', 'min:1', 'max:65535'],
            'price' => ['required', 'numeric', 'min:0'],
            'session' => ['required', Rule::enum(EventSession::class)],
            'category' => ['required', Rule::enum(EventCategory::class)],
            'banner' => ['sometimes', 'nullable', 'image', 'max:10240'],
            'publish' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('price') && is_string($this->input('price'))) {
            $this->merge([
                'price' => EventService::normalizePriceInput($this->input('price')),
            ]);
        }
    }
}

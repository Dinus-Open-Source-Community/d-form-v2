<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesEventCategoryRequest;
use App\Http\Requests\Concerns\NormalizesEventSessionRequest;
use App\Rules\CommaSeparatedEventCategories;
use App\Rules\CommaSeparatedEventSessions;
use App\Services\Event\EventService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    use NormalizesEventCategoryRequest;
    use NormalizesEventSessionRequest;

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
            'session' => ['required', 'string', 'max:2048', new CommaSeparatedEventSessions()],
            'category' => ['required', 'string', 'max:2048', new CommaSeparatedEventCategories()],
            'banner' => ['sometimes', 'nullable', 'image', 'max:10240'],
            'publish' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeNormalizedCategoryFromRequest();
        $this->mergeNormalizedSessionFromRequest();

        if ($this->has('price') && is_string($this->input('price'))) {
            $this->merge([
                'price' => EventService::normalizePriceInput($this->input('price')),
            ]);
        }
    }
}

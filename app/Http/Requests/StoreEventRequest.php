<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesEventCategoryRequest;
use App\Http\Requests\Concerns\NormalizesEventSessionRequest;
use App\Http\Requests\Concerns\SanitizesRichHtmlInput;
use App\Rules\CommaSeparatedEventCategories;
use App\Rules\CommaSeparatedEventSessions;
use App\Services\Event\EventService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    use NormalizesEventCategoryRequest;
    use NormalizesEventSessionRequest;
    use SanitizesRichHtmlInput;

    public function authorize(): bool
    {
        return $this->user()->can('events.create');
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
            'banner' => ['required', 'image', 'max:10240'],
            'publish' => ['sometimes', 'boolean'],
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
            'title.required' => 'Please enter the event title.',
            'title.string' => 'Event title must be valid text.',
            'title.max' => 'Event title cannot be longer than 100 characters.',
            'location.required' => 'Please enter the event location.',
            'location.string' => 'Event location must be valid text.',
            'location.max' => 'Event location cannot be longer than 100 characters.',
            'description.required' => 'Please enter a description for the event.',
            'description.string' => 'Event description must be valid text.',
            'registration_start.required' => 'Please select when registration opens.',
            'registration_start.date' => 'Registration start must be a valid date.',
            'registration_start.before_or_equal' => 'Registration must open before or on the event start date.',
            'registration_end.required' => 'Please select when registration closes.',
            'registration_end.date' => 'Registration end must be a valid date.',
            'registration_end.after' => 'Registration close date must be after registration open date.',
            'registration_end.before_or_equal' => 'Registration must close before or on the event end date.',
            'start_date.required' => 'Please select when the event starts.',
            'start_date.date' => 'Event start date must be a valid date.',
            'start_date.after_or_equal' => 'Event cannot start before registration opens.',
            'end_date.required' => 'Please select when the event ends.',
            'end_date.date' => 'Event end date must be a valid date.',
            'end_date.after_or_equal' => 'Event end date must be on or after the start date.',
            'quota.required' => 'Please enter the maximum number of participants.',
            'quota.integer' => 'Participant quota must be a whole number.',
            'quota.min' => 'Participant quota must be at least 1.',
            'quota.max' => 'Participant quota cannot exceed 65,535.',
            'price.required' => 'Please enter the event price (enter 0 for free events).',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price cannot be negative.',
            'session.required' => 'Please select at least one event session.',
            'session.string' => 'Event session must be valid text.',
            'session.max' => 'Event sessions cannot exceed 2,048 characters.',
            'category.required' => 'Please select at least one event category.',
            'category.string' => 'Event category must be valid text.',
            'category.max' => 'Event categories cannot exceed 2,048 characters.',
            'banner.required' => 'Please upload a banner image for the event.',
            'banner.image' => 'Banner must be an image file (JPG, PNG, etc.).',
            'banner.max' => 'Banner image size cannot exceed 10 MB.',
            'publish.boolean' => 'Publish status must be true or false.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeNormalizedCategoryFromRequest();
        $this->mergeNormalizedSessionFromRequest();
        $this->mergeSanitizedHtml(['description']);

        if ($this->has('price') && is_string($this->input('price'))) {
            $this->merge([
                'price' => EventService::normalizePriceInput($this->input('price')),
            ]);
        }
    }
}

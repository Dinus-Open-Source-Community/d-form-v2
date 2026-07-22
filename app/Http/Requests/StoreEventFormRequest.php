<?php

namespace App\Http\Requests;

use App\Enums\EventFormVisibility;
use App\Models\Event;
use App\Support\FormFieldsRequestValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        $eventParam = $this->route('event');
        $event = $eventParam instanceof Event
            ? $eventParam
            : Event::query()->find($eventParam);

        return $event && $user->can('update', $event);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(
            [
                'title' => 'required|string|max:100',
                'description' => 'required|string',
                'success_content' => 'nullable|string',
                'closed_at' => 'required|date',
                'visible_for' => 'required|array|min:1',
                'visible_for.*' => [Rule::enum(EventFormVisibility::class)],
                'banner_url' => 'nullable|string',
                'banner_caption' => 'nullable|string|max:255',
                'fields' => 'nullable|array',
            ],
            FormFieldsRequestValidation::formMetadataRules(),
            FormFieldsRequestValidation::nestedFieldRules(),
        );
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please enter the form title.',
            'title.string' => 'Form title must be valid text.',
            'title.max' => 'Form title cannot be longer than 100 characters.',
            'description.required' => 'Please enter a description for the form.',
            'description.string' => 'Form description must be valid text.',
            'closed_at.required' => 'Please select when the form closes.',
            'closed_at.date' => 'Form close date must be a valid date.',
            'visible_for.required' => 'Please select who can see this form.',
            'visible_for.array' => 'Form visibility must be a list of options.',
            'visible_for.min' => 'Please select at least one visibility option.',
            'banner_url.string' => 'Banner URL must be valid text.',
            'banner_caption.string' => 'Banner caption must be valid text.',
            'banner_caption.max' => 'Banner caption cannot be longer than 255 characters.',
            'fields.array' => 'Form fields must be a valid list.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $eventParam = $this->route('event');
        $event = $eventParam instanceof Event
            ? $eventParam
            : Event::query()->find($eventParam);

        FormFieldsRequestValidation::afterForFields(
            $validator,
            $this,
            null,
            $event?->id,
        );
    }
}

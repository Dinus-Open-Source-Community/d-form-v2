<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConvertsLegacyDataUrls;
use App\Models\Event;
use App\Models\Form;
use App\Support\FormFieldsRequestValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FieldModifyRequest extends FormRequest
{
    use ConvertsLegacyDataUrls;

    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $eventParam = $this->route('event');
        $formParam = $this->route('form');

        $event = $eventParam instanceof Event
            ? $eventParam
            : Event::query()->select(['id', 'created_by'])->find($eventParam);

        $form = $formParam instanceof Form
            ? $formParam
            : Form::query()->select(['id', 'event_id'])->find($formParam);

        if (!$event || !$form || $form->event_id !== $event->id) {
            return false;
        }

        return $user->can('update', $event);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(
            [
                'fields' => 'required|array',
            ],
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
            'fields.required' => 'Please provide form fields to update.',
            'fields.array' => 'Form fields must be a valid list.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $formParam = $this->route('form');
        $form = $formParam instanceof Form
            ? $formParam
            : ($formParam !== null ? Form::query()->select(['id', 'event_id'])->find($formParam) : null);
        FormFieldsRequestValidation::afterForFields($validator, $this, $form);
    }

    protected function prepareForValidation(): void
    {
        $this->convertLegacyDataUrlsInRequest();
    }
}

<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConvertsLegacyDataUrls;
use App\Http\Requests\Concerns\SanitizesRichHtmlInput;
use App\Models\Event;
use App\Models\Form;
use App\Support\FormFieldsRequestValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FieldModifyRequest extends FormRequest
{
    use ConvertsLegacyDataUrls;
    use SanitizesRichHtmlInput;

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
            : Form::query()->select(['id', 'event_id', 'status', 'created_by'])->find($formParam);

        if (!$event || !$form || $form->event_id !== $event->id) {
            return false;
        }

        // Draft hanya boleh ditulis oleh pembuat event (autosave editor tetap
        // berjalan untuknya; ini bukan syarat "published").
        return $user->can('update', $event) && $form->isDraftVisibleTo($event, $user);
    }

    /**
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     *
     */
    public function rules(): array
    {
        return array_merge(
            [
                // `present` (bukan `required`): array kosong adalah payload sah
                // untuk menghapus field terakhir pada channel autosave fields.
                'fields' => 'present|array',
            ],
            FormFieldsRequestValidation::nestedFieldRules(),
        );
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
            'fields.present' => 'Please provide form fields to update.',
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
        $this->mergeSanitizedFormFieldHtml();
    }
}

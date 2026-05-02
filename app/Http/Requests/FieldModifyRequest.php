<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Support\FormFieldTypeMapping;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FieldModifyRequest extends FormRequest
{
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
            : Event::query()->find($eventParam);

        $form = $formParam instanceof Form
            ? $formParam
            : Form::query()->find($formParam);

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
            $this->field(),
            $this->fieldGlobalMetadata(),
            $this->fieldRulesMetadata()
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $fields = $v->getData()['fields'] ?? $this->input('fields', []);
            if (!is_array($fields)) {
                return;
            }

            foreach ($fields as $index => $field) {
                if (!is_array($field)) {
                    continue;
                }
                if (($field['type'] ?? '') === 'datePicker') {
                    $min = $field['metadata']['rules']['min_date'] ?? null;
                    $max = $field['metadata']['rules']['max_date'] ?? null;
                    if (is_string($min) && $min !== '' && is_string($max) && $max !== '' && strtotime($max) < strtotime($min)) {
                        $v->errors()->add(
                            "fields.{$index}.metadata.rules.max_date",
                            "Tanggal maksimal tidak boleh lebih kecil dari tanggal minimal ({$min})."
                        );
                    }
                }
            }

            $formParam = $this->route('form');
            $form = $formParam instanceof Form
                ? $formParam
                : ($formParam !== null ? Form::query()->find($formParam) : null);
            if (!$form) {
                return;
            }

            $names = collect($fields)->pluck('name')->filter(fn ($n) => $n !== null && $n !== '')->map(fn ($n) => (string) $n);
            if ($names->count() !== $names->unique()->count()) {
                $v->errors()->add('fields', __('validation.distinct', ['attribute' => 'name']));
            }

            foreach ($fields as $index => $field) {
                if (!is_array($field)) {
                    continue;
                }
                $name = $field['name'] ?? null;
                if (!is_string($name) || $name === '') {
                    continue;
                }
                $id = $field['id'] ?? null;
                $q = FormField::query()
                    ->where('form_id', $form->id)
                    ->where('name', $name);
                if (is_string($id) && $id !== '') {
                    $q->where('id', '!=', $id);
                }
                if ($q->exists()) {
                    $v->errors()->add("fields.{$index}.name", __('validation.unique', ['attribute' => 'name']));
                }
            }
        });
    }

    public function field(): array
    {
        return [
            'fields.*.id' => 'nullable|uuid',
            'fields.*.label' => 'required|string|max:100',
            'fields.*.description' => 'nullable|string|max:1000',
            'fields.*.name' => 'required|string|max:100',
            'fields.*.type' => ['required', 'string', Rule::in(FormFieldTypeMapping::API_TYPES)],
            'fields.*.metadata' => 'required|array',
            'fields.*.order' => 'required|integer|min:0',
        ];
    }

    public function fieldGlobalMetadata(): array
    {
        return [
            'fields.*.metadata.placeholder' => 'nullable',
            'fields.*.metadata.type' => [
                'required_if:fields.*.type,input',
                'prohibited_unless:fields.*.type,input',
                Rule::in('text', 'number', 'email', 'password', 'tel'),
            ],
            'fields.*.metadata.is_multiple' => [
                'required_if:fields.*.type,select',
                'prohibited_unless:fields.*.type,select',
                'boolean',
            ],
            'fields.*.metadata.options' => [
                'required_if:fields.*.type,radio',
                'required_if:fields.*.type,checkbox',
                'nullable',
                'string',
                'prohibited_unless:fields.*.type,radio,checkbox',
            ],
        ];
    }

    public function fieldRulesMetadata(): array
    {
        return [
            'fields.*.metadata.rules' => 'nullable|array',

            'fields.*.metadata.rules.required' => 'nullable|boolean',

            'fields.*.metadata.rules.min' => [
                'nullable',
                'integer',
                'min:0',
                'prohibited_unless:fields.*.type,input,textarea'
            ],
            'fields.*.metadata.rules.max' => [
                'nullable',
                'integer',
                'gt:fields.*.metadata.rules.min',
                'prohibited_unless:fields.*.type,input,textarea'
            ],

            'fields.*.metadata.rules.regex' => [
                'nullable',
                'string',
                'prohibited_unless:fields.*.type,input'
            ],

            'fields.*.metadata.rules.in' => [
                'nullable',
                'string',
                'prohibited_unless:fields.*.type,select'
            ],

            'fields.*.metadata.rules.multiple' => [
                'nullable',
                'boolean',
                'prohibited_unless:fields.*.type,select,fileUpload'
            ],

            'fields.*.metadata.rules.min_date' => [
                'nullable',
                'date',
                'prohibited_unless:fields.*.type,datePicker'
            ],
            'fields.*.metadata.rules.max_date' => [
                'nullable',
                'date',
                'prohibited_unless:fields.*.type,datePicker'
            ],

            'fields.*.metadata.rules.mimes' => [
                'nullable',
                'string',
                'prohibited_unless:fields.*.type,fileUpload'
            ],
            'fields.*.metadata.rules.max_size' => [
                'nullable',
                'integer',
                'min:1',
                'prohibited_unless:fields.*.type,fileUpload'
            ],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\EventFormVisibility;
use App\Http\Requests\Concerns\ConvertsLegacyDataUrls;
use App\Http\Requests\Concerns\SanitizesRichHtmlInput;
use App\Models\Event;
use App\Models\Form;
use App\Support\FormFieldsRequestValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEventFormRequest extends FormRequest
{
    use ConvertsLegacyDataUrls;
    use SanitizesRichHtmlInput;

    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        $event = $this->resolvedEvent();
        $form = $this->resolvedForm();
        if (!$event || !$form || $form->event_id !== $event->id) {
            return false;
        }

        return $user->can('update', $event);
    }

    /**
     * Autosave parsial hanya untuk draft: builder menyimpan header yang belum
     * lengkap, jadi field wajib (title/description/closed_at) tidak boleh
     * dipaksakan. `publish` selalu memakai aturan penuh, dan `autosave` pada
     * form published diabaikan (aturan penuh seperti sebelumnya).
     */
    public function isDraftAutosave(): bool
    {
        if ($this->boolean('publish') || ! $this->boolean('autosave')) {
            return false;
        }

        return $this->resolvedForm()?->isDraft() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->isDraftAutosave()) {
            return array_merge(
                [
                    'autosave' => 'sometimes|boolean',
                    'publish' => 'sometimes|boolean',
                    'title' => 'nullable|string|max:100',
                    'description' => 'nullable|string',
                    'success_content' => 'nullable|string',
                    'closed_at' => 'nullable|date',
                    // `min:1` hanya berlaku bila key dikirim; key yang tidak ada
                    // tidak divalidasi sama sekali oleh Validator.
                    'visible_for' => 'nullable|array|min:1',
                    'visible_for.*' => [Rule::enum(EventFormVisibility::class)],
                    'banner_url' => 'nullable|string',
                    'banner_caption' => 'nullable|string|max:255',
                    'fields' => 'nullable|array',
                ],
                FormFieldsRequestValidation::formMetadataRules(requirePurpose: false),
                FormFieldsRequestValidation::nestedFieldRules(),
            );
        }

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
                // Sama seperti EventService::create/update: `publish` memindahkan status
                // form ke Published hanya setelah seluruh validasi di request ini lolos.
                'publish' => 'sometimes|boolean',
                'autosave' => 'sometimes|boolean',
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
        $event = $this->resolvedEvent();
        $form = $this->resolvedForm();

        FormFieldsRequestValidation::afterForFields(
            $validator,
            $this,
            $form,
            $event?->id ?? $form?->event_id,
        );
    }

    private function resolvedEvent(): ?Event
    {
        $eventParam = $this->route('event');

        return $eventParam instanceof Event
            ? $eventParam
            : Event::query()->select(['id', 'created_by'])->find($eventParam);
    }

    private function resolvedForm(): ?Form
    {
        $formParam = $this->route('form');

        return $formParam instanceof Form
            ? $formParam
            : Form::query()->select(['id', 'event_id', 'status'])->find($formParam);
    }

    protected function prepareForValidation(): void
    {
        $this->convertLegacyDataUrlsInRequest();
        $this->mergeSanitizedHtml(['success_content']);
        $this->mergeSanitizedFormFieldHtml();
    }
}

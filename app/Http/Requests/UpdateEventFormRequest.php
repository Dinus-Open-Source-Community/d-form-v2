<?php

namespace App\Http\Requests;

use App\Enums\EventFormVisibility;
use App\Models\Event;
use App\Models\Form;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventFormRequest extends FormRequest
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

        $formParam = $this->route('form');
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
        return [
            'title' => 'required|string|max:100',
            'description' => 'required|string',
            'closed_at' => 'required|date',
            'visible_for' => 'required|array|min:1',
            'visible_for.*' => [Rule::enum(EventFormVisibility::class)],
            'banner_url' => 'nullable|string',
            'banner_caption' => 'nullable|string|max:255',
            'fields' => 'nullable|array',
            'fields.*.id' => 'nullable|string',
            'fields.*.type' => 'required|string',
            'fields.*.label' => 'required|string',
            'fields.*.name' => 'required|string',
            'fields.*.order' => 'required|integer',
            'fields.*.metadata' => 'nullable|array',
        ];
    }
}

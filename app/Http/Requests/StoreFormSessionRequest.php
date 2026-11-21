<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StoreFormSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $eventParam = $this->route('event');
        $event = $eventParam instanceof Event ? $eventParam : Event::query()->find($eventParam);

        return $user !== null && $event !== null && $user->can('update', $event);
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'session_date' => [
                'required',
                'date',
                Rule::unique('form_sessions', 'session_date')->where(function (Builder $query): void {
                    $form = $this->route('form');
                    $formId = $form instanceof \App\Models\Form ? $form->id : $form;
                    $query->where('form_id', $formId);
                }),
            ],
            'label' => 'nullable|string|max:100',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\FormAnswerReviewStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Review satu submission form: status review hanya boleh nilai enum yang sah.
 * Otorisasi sama seperti controller sebelumnya (policy review + update event).
 */
class ReviewFormAnswerRequest extends FormRequest
{
    /**
     * `403` policy tetap lebih dulu, lalu `404` bila form/answer tidak cocok
     * dengan URL, baru validasi — urutan yang sama dengan controller sebelumnya.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $event = $this->route('event');
        $form = $this->route('form');
        $formAnswer = $this->route('formAnswer');

        if (
            $user === null
            || ! $event instanceof Event
            || ! $form instanceof Form
            || ! $formAnswer instanceof FormAnswer
            || ! $user->can('review', $formAnswer)
            || ! $user->can('update', $event)
        ) {
            return false;
        }

        abort_unless($form->event_id === $event->id, 404);
        abort_unless($formAnswer->form_id === $form->id, 404);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review_status' => ['required', Rule::enum(FormAnswerReviewStatus::class)],
        ];
    }
}

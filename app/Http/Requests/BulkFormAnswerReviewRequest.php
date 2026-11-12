<?php

namespace App\Http\Requests;

use App\Enums\FormAnswerReviewStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an explicitly selected, bounded batch of form-answer review decisions.
 */
final class BulkFormAnswerReviewRequest extends FormRequest
{
    /** Authorize the organizer and reject a form from another event before validation. */
    public function authorize(): bool
    {
        $user = $this->user();
        $event = $this->route('event');
        $form = $this->route('form');

        if (
            ! $user instanceof User
            || ! $event instanceof Event
            || ! $form instanceof Form
            || ! $user->can('events.view')
            || ! $user->can('update', $event)
        ) {
            return false;
        }

        abort_unless((string) $form->event_id === (string) $event->id, 404);

        return true;
    }

    /**
     * Validate only explicit, unique UUIDs and an accepted/rejected decision.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'submission_ids' => ['required', 'array', 'min:1', 'max:100'],
            'submission_ids.*' => ['required', 'string', 'uuid', 'distinct'],
            'review_status' => [
                'required',
                Rule::in([
                    FormAnswerReviewStatus::Accepted->value,
                    FormAnswerReviewStatus::Rejected->value,
                ]),
            ],
        ];
    }
}

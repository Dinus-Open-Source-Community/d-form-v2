<?php

namespace App\Http\Requests;

use App\Enums\FormAnswerReviewStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Review satu submission form: status review hanya boleh nilai enum yang sah.
 * Otorisasi sama seperti controller sebelumnya (policy review + update event).
 */
class ReviewFormAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('review', $this->route('formAnswer'))
            && $user->can('update', $this->route('event'));
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

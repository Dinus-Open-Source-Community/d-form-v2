<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('recruitment_application') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ratingRule = ['required', 'integer', Rule::in([1, 2, 3, 4, 5])];

        return [
            'rating_registration_ease' => $ratingRule,
            'rating_info_clarity' => $ratingRule,
            'rating_tracking_ease' => $ratingRule,
            'rating_interview_experience' => $ratingRule,
            'rating_staff_service' => $ratingRule,
            'feedback_text' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

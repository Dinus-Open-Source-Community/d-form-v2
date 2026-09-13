<?php

namespace App\Http\Requests\Recruitment;

use App\Enums\Recruitment\EvaluationRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecruitmentEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'speaking_score' => ['required', 'integer', 'min:1', 'max:10'],
            'technical_score' => ['required', 'integer', 'min:1', 'max:10'],
            'attitude_score' => ['required', 'integer', 'min:1', 'max:10'],
            'recommendation' => ['required', 'string', Rule::enum(EvaluationRecommendation::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

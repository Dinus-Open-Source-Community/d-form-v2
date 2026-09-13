<?php

namespace App\Http\Requests\Recruitment;

use App\Enums\Recruitment\RecruitmentPeriodStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRecruitmentPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.periods.list') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(RecruitmentPeriodStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

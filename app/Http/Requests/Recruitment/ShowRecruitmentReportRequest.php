<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class ShowRecruitmentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('recruitment.reports.view') ?? false)
            || ($this->user()?->can('recruitment.reports.export') ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_id' => ['nullable', 'uuid'],
        ];
    }
}

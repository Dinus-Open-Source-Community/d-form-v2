<?php

namespace App\Http\Requests\Recruitment;

use App\Enums\Recruitment\ApplicationStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRecruitmentApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.applications.list') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'period_id' => ['nullable', 'uuid'],
            'division_id' => ['nullable', 'uuid'],
            'stage' => ['nullable', Rule::enum(ApplicationStage::class)],
            'queue' => ['nullable', 'string', Rule::in(['screening', 'revision', 'interview', 'final', 'done'])],
            'semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

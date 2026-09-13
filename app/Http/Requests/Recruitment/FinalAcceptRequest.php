<?php

namespace App\Http\Requests\Recruitment;

use App\Enums\Recruitment\MembershipType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinalAcceptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application !== null
            && $this->user()?->can('decideFinal', $application);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'membership_type' => ['required', Rule::enum(MembershipType::class)],
            'final_division_id' => ['required', 'uuid', 'exists:recruitment_divisions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'membership_type.required' => 'Tipe keanggotaan wajib dipilih.',
            'final_division_id.required' => 'Divisi penempatan wajib dipilih.',
        ];
    }
}

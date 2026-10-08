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
            'whatsapp_group_url' => ['nullable', 'string', 'max:255', 'url', 'starts_with:https://'],
            'include_group_link' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('whatsapp_group_url')) {
            $raw = $this->input('whatsapp_group_url');
            if (is_string($raw)) {
                $clean = trim(strip_tags($raw));
                $clean = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $clean);
                $this->merge(['whatsapp_group_url' => trim($clean) === '' ? null : trim($clean)]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'membership_type.required' => 'Tipe keanggotaan wajib dipilih.',
            'final_division_id.required' => 'Divisi penempatan wajib dipilih.',
            'whatsapp_group_url.url' => 'Link grup WA harus berupa URL valid (mis. https://chat.whatsapp.com/...).',
            'whatsapp_group_url.starts_with' => 'Link grup WA harus memakai https://.',
        ];
    }
}

<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecruitmentPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.periods.edit') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['whatsapp_group_url', 'whatsapp_group_aa_url', 'whatsapp_group_member_url'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $this->merge([$field => self::normalizeWhatsappUrl($this->input($field))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at'],
            'interview_starts_at' => ['nullable', 'date'],
            'interview_ends_at' => ['nullable', 'date', 'after_or_equal:interview_starts_at'],
            'finalization_deadline_at' => ['nullable', 'date'],
            'banner' => ['sometimes', 'nullable', 'image', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'whatsapp_group_url' => ['nullable', 'string', 'max:255', 'url', 'starts_with:https://'],
            'whatsapp_group_aa_url' => ['nullable', 'string', 'max:255', 'url', 'starts_with:https://'],
            'whatsapp_group_member_url' => ['nullable', 'string', 'max:255', 'url', 'starts_with:https://'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'banner.image' => 'Banner harus berupa file gambar.',
            'banner.max' => 'Ukuran banner tidak boleh lebih dari 10 MB.',
            'banner.mimes' => 'Banner harus berformat JPG, JPEG, PNG, atau WEBP.',
            'whatsapp_group_url.url' => 'Link grup WA harus berupa URL valid (mis. https://chat.whatsapp.com/...).',
            'whatsapp_group_url.starts_with' => 'Link grup WA harus memakai https://.',
            'whatsapp_group_aa_url.url' => 'Link grup WA AA harus berupa URL valid (mis. https://chat.whatsapp.com/...).',
            'whatsapp_group_aa_url.starts_with' => 'Link grup WA AA harus memakai https://.',
            'whatsapp_group_member_url.url' => 'Link grup WA Member harus berupa URL valid (mis. https://chat.whatsapp.com/...).',
            'whatsapp_group_member_url.starts_with' => 'Link grup WA Member harus memakai https://.',
        ];
    }

    private static function normalizeWhatsappUrl(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $clean = trim(strip_tags($value));
        $clean = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $clean);

        return trim($clean) === '' ? null : trim($clean);
    }
}

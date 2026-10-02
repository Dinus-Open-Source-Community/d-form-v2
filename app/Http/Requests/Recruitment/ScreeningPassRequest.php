<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class ScreeningPassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application !== null
            && $this->user()?->can('screen', $application);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'whatsapp_group_url' => ['nullable', 'string', 'max:255', 'url', 'starts_with:https://'],
            'include_group_link' => ['nullable', 'boolean'],
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
}

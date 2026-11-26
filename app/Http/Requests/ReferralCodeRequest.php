<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Form;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Aturan bersama kode referral: otorisasi event, normalisasi input, dan validasi format. */
abstract class ReferralCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $event = $this->route('event');
        $form = $this->route('form');

        if (! $user || ! $event instanceof Event || ! $form instanceof Form) {
            return false;
        }

        if ($form->event_id !== $event->id) {
            return false;
        }

        return $user->can('update', $event);
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['prefix', 'body'] as $key) {
            $value = $this->input($key);
            if (is_string($value)) {
                $normalized[$key] = strtoupper(trim($value));
            }
        }

        $attributionName = $this->input('attribution_name');
        if (is_string($attributionName)) {
            $normalized['attribution_name'] = trim($attributionName) === '' ? null : trim($attributionName);
        }

        foreach (['starts_at', 'expires_at', 'assigned_user_id', 'usage_limit', 'discount_type', 'discount_value'] as $key) {
            if ($this->input($key) === '') {
                $normalized[$key] = null;
            }
        }

        $this->merge($normalized);
    }

    /** Diskon harus berpasangan: jenis tanpa nilai atau nilai tanpa jenis ditolak. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasType = $this->input('discount_type') !== null;
            $hasValue = $this->input('discount_value') !== null;

            if ($hasType !== $hasValue) {
                $validator->errors()->add('discount_value', 'Isi jenis dan nilai diskon bersama.');
            }
        });
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    protected function codeRules(bool $requireIdentity): array
    {
        $prefixMin = (int) config('events.forms.referral.prefix_min_length', 2);
        $prefixMax = (int) config('events.forms.referral.prefix_max_length', 12);
        $bodyMin = (int) config('events.forms.referral.body_min_length', 4);
        $bodyMax = (int) config('events.forms.referral.body_max_length', 16);

        $present = $requireIdentity ? ['required'] : ['sometimes', 'required'];

        return [
            'attribution_mode' => [...$present, 'string', Rule::in(['user', 'name'])],
            'assigned_user_id' => [
                'nullable',
                'uuid',
                'required_if:attribution_mode,user',
                'prohibited_if:attribution_mode,name',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'attribution_name' => [
                'nullable',
                'string',
                'max:150',
                'required_if:attribution_mode,name',
                'prohibited_if:attribution_mode,user',
            ],
            'prefix' => [
                ...$present,
                'string',
                'regex:/^[A-Z0-9]{' . $prefixMin . ',' . $prefixMax . '}$/',
            ],
            'generation_mode' => [...$present, 'string', Rule::in(['random', 'manual'])],
            'body' => [
                'nullable',
                'string',
                'required_if:generation_mode,manual',
                'regex:/^[A-Z0-9]{' . $bodyMin . ',' . $bodyMax . '}$/',
            ],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'discount_type' => ['nullable', 'string', Rule::in(['percentage', 'fixed'])],
            'discount_value' => [
                'nullable',
                'integer',
                'min:1',
                Rule::when(fn (): bool => $this->input('discount_type') === 'percentage', ['max:100']),
            ],
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'expires_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceScanStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Event|null $event */
        $event = $this->route('event');

        return $event instanceof Event && $this->user() !== null && $this->user()->can('update', $event);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'submission_id' => ['nullable', 'string', 'uuid'],
            'registration_code' => ['nullable', 'string', 'max:32'],
            'raw_payload' => ['nullable', 'string', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasSubmissionId = filled(trim((string) $this->input('submission_id', '')));
            $hasCode = filled(trim((string) $this->input('registration_code', '')));
            $hasRaw = filled(trim((string) $this->input('raw_payload', '')));

            if (! $hasSubmissionId && ! $hasCode && ! $hasRaw) {
                $validator->errors()->add(
                    'payload',
                    __('Provide a QR payload, registration code, or submission ID.'),
                );
            }
        });
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Perubahan status event dari halaman detail (publish / kembali ke draft). */
class UpdateEventStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('events.edit');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(EventStatus::class)],
        ];
    }
}

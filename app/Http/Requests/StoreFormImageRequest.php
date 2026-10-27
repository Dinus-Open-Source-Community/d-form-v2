<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Unggahan gambar di editor form builder; otorisasi mengikuti policy event
 * (admin yang boleh mengubah event boleh mengunggah gambar form-nya).
 */
class StoreFormImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('event')) ?? false;
    }

    /**
     * Whitelist gambar tidak boleh dilonggarkan: maksimal 2 MB.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:2048'],
        ];
    }
}

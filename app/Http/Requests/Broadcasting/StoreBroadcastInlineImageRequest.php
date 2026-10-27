<?php

namespace App\Http\Requests\Broadcasting;

use App\Services\Broadcasting\BroadcastInlineImageService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inline image editor broadcast (multipart field `file`); otorisasi `update`
 * pada EmailBroadcast, sama seperti yang dicek controller sebelumnya.
 */
class StoreBroadcastInlineImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('broadcast')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,gif,webp',
                'max:'.BroadcastInlineImageService::MAX_UPLOAD_KB,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih gambar terlebih dahulu.',
            'file.image' => 'File harus berupa gambar.',
            'file.mimes' => 'Gambar harus PNG, JPG, GIF, atau WebP.',
            'file.max' => 'Gambar inline melebihi 2 MB.',
        ];
    }
}

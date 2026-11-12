<?php

namespace App\Http\Requests;

use App\Rules\SafeUploadFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\UploadPurpose;

class UpdateDashboardProfileAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'avatar' => SafeUploadFile::rules(true, UploadPurpose::Image),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Pilih berkas gambar terlebih dahulu.',
            'avatar.image' => 'Unggahan harus berupa gambar.',
            'avatar.mimes' => 'Gunakan JPEG, PNG, GIF, atau WebP.',
            'avatar.max' => 'Ukuran gambar maksimal 5 MB.',
        ];
    }
}

<?php

namespace App\Http\Requests\Broadcasting;

use App\Rules\SafeUploadFile;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\UploadPurpose;

class StoreBroadcastAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('email-broadcast.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => SafeUploadFile::rules(true, UploadPurpose::BroadcastAttachment),
        ];
    }
}

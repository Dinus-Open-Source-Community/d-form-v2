<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Form;
use App\Rules\SafeUploadFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\UploadPurpose;

/**
 * Unggahan gambar di editor form builder; otorisasi mengikuti policy event
 * (admin yang boleh mengubah event boleh mengunggah gambar form-nya).
 */
class StoreFormImageRequest extends FormRequest
{
    /**
     * `404` form bukan milik event tetap dijawab sebelum policy `403`, sama
     * seperti urutan controller sebelum validasi dipindahkan ke sini.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');
        $form = $this->route('form');

        abort_unless(
            $event instanceof Event && $form instanceof Form && $form->event_id === $event->id,
            404
        );

        if (! $form->isDraftVisibleTo($event, $this->user())) {
            return false;
        }

        return $this->user()?->can('update', $event) ?? false;
    }

    /**
     * Whitelist gambar memakai batas global 5 MB.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => SafeUploadFile::rules(true, UploadPurpose::Image),
        ];
    }
}

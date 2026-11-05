<?php

namespace App\Services\Registration;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Services\Upload\SafeUploadStorage;
use App\Services\Upload\UploadStorageTarget;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Stores registration uploads on the private disk and resolves routed access
 * URLs. The public disk is read only as a legacy fallback so files uploaded
 * before the private-disk change stay reachable through the authorized route.
 */
final class FormAnswerFileStorage
{
    private const DIRECTORY_PREFIX = 'form-uploads';

    public function __construct(private readonly SafeUploadStorage $uploads)
    {
    }

    /** Store one validated upload privately and return its relative path. */
    public function store(UploadedFile $file, Form $form): string
    {
        return $this->uploads->store(
            $file,
            UploadStorageTarget::local(self::DIRECTORY_PREFIX.'/'.$form->id),
        );
    }

    /**
     * Resolve the disk and path for a stored answer field.
     *
     * @return array{disk: string, path: string}|null
     */
    public function locate(FormAnswer $answer, string $field): ?array
    {
        if (! FormField::withTrashed()
            ->where('form_id', $answer->form_id)
            ->where('name', $field)
            ->where('input_type', 'fileUpload')
            ->exists()) {
            return null;
        }

        $path = $this->storedPath($answer, $field);

        if ($path === null) {
            return null;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return ['disk' => $disk, 'path' => $path];
            }
        }

        return null;
    }

    /**
     * Replace stored private paths with URLs for an already authenticated page.
     *
     * @param  array<string, mixed>  $answers
     * @param  list<string>  $fileFieldNames
     * @return array<string, mixed>
     */
    public function answerUrls(FormAnswer $answer, array $answers, array $fileFieldNames): array
    {
        return $this->replacePaths($answer, $answers, $fileFieldNames, temporary: false);
    }

    /** URL usable by an authenticated session (same origin, no expiry). */
    public function relativeUrl(FormAnswer $answer, string $field): string
    {
        return route('dashboard.form-answers.files', $this->routeParameters($answer, $field), false);
    }

    /** Time-limited signed URL for email/CSV contexts. */
    public function temporaryUrl(FormAnswer $answer, string $field): string
    {
        return URL::temporarySignedRoute(
            'dashboard.form-answers.files',
            now()->addMinutes((int) config('events.forms.upload_temporary_url_ttl_minutes', 10080)),
            $this->routeParameters($answer, $field),
        );
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  list<string>  $fileFieldNames
     * @return array<string, mixed>
     */
    private function replacePaths(FormAnswer $answer, array $answers, array $fileFieldNames, bool $temporary): array
    {
        $prefix = self::DIRECTORY_PREFIX.'/'.$answer->form_id.'/';

        foreach ($fileFieldNames as $field) {
            $value = $answers[$field] ?? null;

            if (! is_string($value) || ! str_starts_with($value, $prefix)) {
                continue;
            }

            $answers[$field] = $temporary
                ? $this->temporaryUrl($answer, $field)
                : $this->relativeUrl($answer, $field);
        }

        return $answers;
    }

    /**
     * @return array{formAnswer: string, field: string, filename: string}
     */
    private function routeParameters(FormAnswer $answer, string $field): array
    {
        $path = $this->storedPath($answer, $field);

        return [
            'formAnswer' => (string) $answer->getKey(),
            'field' => $field,
            'filename' => $path === null ? 'file' : basename($path),
        ];
    }

    private function storedPath(FormAnswer $answer, string $field): ?string
    {
        $answers = is_array($answer->answers) ? $answer->answers : [];
        $value = $answers[$field] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        $prefix = self::DIRECTORY_PREFIX.'/'.$answer->form_id.'/';

        return str_starts_with($value, $prefix) ? $value : null;
    }
}

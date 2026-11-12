<?php

namespace App\Services\Registration;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Enums\UploadPurpose;
use App\Services\Upload\SafeUploadStorage;
use App\Services\Upload\UploadStorageTarget;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

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
            UploadPurpose::FormAnswerFile,
        );
    }

    /**
     * Store every file for one answer field, removing earlier paths when any
     * later write fails so a field can never be partially persisted.
     *
     * @param list<UploadedFile> $files
     * @return list<string>
     */
    public function storeMany(array $files, Form $form): array
    {
        $storedPaths = [];

        try {
            foreach ($files as $file) {
                $storedPaths[] = $this->store($file, $form);
            }
        } catch (Throwable $exception) {
            $this->deletePaths($storedPaths);

            throw $exception;
        }

        return $storedPaths;
    }

    /**
     * Remove mapped upload paths after a later submission validation failure.
     *
     * @param  array<string, mixed>  $answers
     */
    public function discardMappedUploads(Form $form, array $answers): void
    {
        $paths = [];
        $prefix = self::DIRECTORY_PREFIX.'/'.$form->id.'/';

        $collectPaths = function (mixed $value) use (&$collectPaths, &$paths, $prefix): void {
            if (is_array($value)) {
                foreach ($value as $nestedValue) {
                    $collectPaths($nestedValue);
                }

                return;
            }

            if (is_string($value) && str_starts_with($value, $prefix)) {
                $paths[] = $value;
            }
        };

        $collectPaths($answers);
        $this->deletePaths(array_values(array_unique($paths)));
    }

    /**
     * Resolve the disk and path for a stored answer field.
     *
     * @return array{disk: string, path: string}|null
     */
    public function locate(FormAnswer $answer, string $field, ?string $filename = null): ?array
    {
        if (! FormField::withTrashed()
            ->where('form_id', $answer->form_id)
            ->where('name', $field)
            ->where('input_type', 'fileUpload')
            ->exists()) {
            return null;
        }

        $path = $this->storedPath($answer, $field, $filename);

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

    /**
     * Replace every stored path with an individually signed URL for exports
     * and email summaries.
     *
     * @param  array<string, mixed>  $answers
     * @param  list<string>  $fileFieldNames
     * @return array<string, mixed>
     */
    public function temporaryAnswerUrls(FormAnswer $answer, array $answers, array $fileFieldNames): array
    {
        return $this->replacePaths($answer, $answers, $fileFieldNames, temporary: true);
    }

    /** URL usable by an authenticated session (same origin, no expiry). */
    public function relativeUrl(FormAnswer $answer, string $field, ?string $filename = null): string
    {
        return route('dashboard.form-answers.files', $this->routeParameters($answer, $field, $filename), false);
    }

    /** Time-limited signed URL for email/CSV contexts. */
    public function temporaryUrl(FormAnswer $answer, string $field, ?string $filename = null): string
    {
        return URL::temporarySignedRoute(
            'dashboard.form-answers.files',
            now()->addMinutes((int) config('events.forms.upload_temporary_url_ttl_minutes', 10080)),
            $this->routeParameters($answer, $field, $filename),
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

            if (is_array($value)) {
                $answers[$field] = array_values(array_filter(
                    array_map(
                        fn (mixed $path): ?string => $this->urlForStoredPath(
                            $answer,
                            $field,
                            $path,
                            $prefix,
                            $temporary,
                        ),
                        $value,
                    ),
                    static fn (?string $url): bool => $url !== null,
                ));

                continue;
            }

            $answers[$field] = $this->urlForStoredPath($answer, $field, $value, $prefix, $temporary);
        }

        return $answers;
    }

    /**
     * @return array{formAnswer: string, field: string, filename: string}
     */
    private function routeParameters(FormAnswer $answer, string $field, ?string $filename = null): array
    {
        $path = $this->storedPath($answer, $field);

        return [
            'formAnswer' => (string) $answer->getKey(),
            'field' => $field,
            'filename' => $filename ?? ($path === null ? 'file' : basename($path)),
        ];
    }

    private function storedPath(FormAnswer $answer, string $field, ?string $filename = null): ?string
    {
        $answers = is_array($answer->answers) ? $answer->answers : [];
        $value = $answers[$field] ?? null;

        $paths = is_array($value) ? $value : [$value];
        $prefix = self::DIRECTORY_PREFIX.'/'.$answer->form_id.'/';

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '' || ! str_starts_with($path, $prefix)) {
                continue;
            }

            $pathFilename = basename($path);
            if ($path !== $prefix.$pathFilename || ! $this->isSafeFilename($pathFilename)) {
                continue;
            }

            if ($filename === null || ($this->isSafeFilename($filename) && $pathFilename === $filename)) {
                return $path;
            }
        }

        return null;
    }

    private function urlForStoredPath(
        FormAnswer $answer,
        string $field,
        mixed $value,
        string $prefix,
        bool $temporary,
    ): ?string {
        if (! is_string($value) || ! str_starts_with($value, $prefix)) {
            return null;
        }

        $filename = basename($value);
        if (! $this->isSafeFilename($filename) || $value !== $prefix.$filename) {
            return null;
        }

        return $temporary
            ? $this->temporaryUrl($answer, $field, $filename)
            : $this->relativeUrl($answer, $field, $filename);
    }

    private function isSafeFilename(string $filename): bool
    {
        return $filename !== ''
            && $filename !== '.'
            && $filename !== '..'
            && basename($filename) === $filename
            && ! str_contains($filename, '\\')
            && preg_match('/[\x00-\x1F\x7F]/', $filename) !== 1;
    }

    /**
     * Delete paths independently so cleanup failures never replace the
     * exception that caused the original upload operation to fail.
     *
     * @param  list<string>  $paths
     */
    private function deletePaths(array $paths): void
    {
        try {
            $disk = Storage::disk(UploadPurpose::FormAnswerFile->disk());
        } catch (Throwable $cleanupException) {
            report($cleanupException);

            return;
        }

        foreach ($paths as $path) {
            try {
                $disk->delete($path);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }
        }
    }
}

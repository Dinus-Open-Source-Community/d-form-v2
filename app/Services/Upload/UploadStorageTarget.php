<?php

namespace App\Services\Upload;

/** Identifies the destination disk and directory for a sanitized upload. */
final class UploadStorageTarget
{
    public function __construct(
        public readonly string $directory,
        public readonly string $disk,
    ) {
    }

    /** Creates a target for files that may be served through public storage. */
    public static function public(string $directory): self
    {
        return new self($directory, 'public');
    }

    /** Creates a target for files that must remain on the private local disk. */
    public static function local(string $directory): self
    {
        return new self($directory, 'local');
    }
}

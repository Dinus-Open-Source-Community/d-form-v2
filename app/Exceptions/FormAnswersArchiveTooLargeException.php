<?php

namespace App\Exceptions;

use RuntimeException;

/** Arsip jawaban melewati batas jumlah file/byte yang dikonfigurasi. */
final class FormAnswersArchiveTooLargeException extends RuntimeException
{
    public function __construct(
        public readonly int $fileCount,
        public readonly int $byteCount,
    ) {
        parent::__construct('Form answers archive exceeds the configured limits.');
    }
}

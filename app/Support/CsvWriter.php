<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/** Central CSV row writer with formula neutralization and RFC 4180 escaping. */
final class CsvWriter
{
    /**
     * Sanitize every cell and write it with PHP's proprietary escape disabled.
     *
     * @param  resource  $stream
     * @param  array<array-key, mixed>  $fields
     */
    public static function writeRow(mixed $stream, array $fields): void
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('CsvWriter requires an open stream.');
        }

        $sanitizedFields = array_map(CsvCellSanitizer::sanitize(...), $fields);

        if (fputcsv($stream, $sanitizedFields, ',', '"', '') === false) {
            throw new RuntimeException('Unable to write CSV row.');
        }
    }
}

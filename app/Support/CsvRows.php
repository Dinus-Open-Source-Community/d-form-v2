<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/** Guard baca CSV unggahan: batas panjang baris, batas jumlah baris, dan penolakan baris ber-byte biner. */
final class CsvRows
{
    /** Batas panjang satu baris; baris lebih panjang dipotong oleh `fgetcsv`. */
    public const MAX_LINE_BYTES = 8192;

    /**
     *
     * Iterasi baris CSV tanpa menampung seluruh file.
     *
     * @param  callable(array<int, mixed>, int): void  $onRow  Indeks baris dimulai 0 (header ikut).
     * @param  int|null  $maxRows  Default dari config `events.forms.dataset_csv_max_rows`.
     *
     */
    public static function each(UploadedFile $file, callable $onRow, ?int $maxRows = null): void
    {
        $limit = $maxRows ?? (int) config('events.forms.dataset_csv_max_rows', 10000);
        $path = $file->getRealPath();
        if ($path === false) {
            return;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        try {
            $index = 0;

            while (($row = fgetcsv($handle, self::MAX_LINE_BYTES)) !== false) {
                if (! is_array($row)) {
                    continue;
                }

                // Indeks 0 diperlakukan sebagai baris header dan tidak dihitung,
                // sehingga batas berlaku untuk baris data.
                if ($index > $limit) {
                    break;
                }

                if (self::rowContainsBinary($row)) {
                    $index++;

                    continue;
                }

                $onRow($row, $index);
                $index++;
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param  array<int, mixed>  $row */
    public static function rowContainsBinary(array $row): bool
    {
        foreach ($row as $cell) {
            if (is_string($cell) && (str_contains($cell, "\0") || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $cell) === 1)) {
                return true;
            }
        }

        return false;
    }
}

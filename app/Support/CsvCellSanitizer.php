<?php

namespace App\Support;

/**
 * Menetralkan nilai sel CSV agar tidak dieksekusi sebagai formula oleh spreadsheet.
 */
final class CsvCellSanitizer
{
    /**
     * Memberi awalan apostrof pada sel yang berpotensi formula; sel lain tidak diubah.
     */
    public static function sanitize(mixed $value): string
    {
        $cell = (string) $value;
        $probe = ltrim($cell, ' ');

        $isFormula = str_starts_with($probe, '=')
            || str_starts_with($probe, '+')
            || str_starts_with($probe, '-')
            || str_starts_with($probe, '@')
            || str_starts_with($probe, "\t")
            || str_starts_with($probe, "\r");

        return $isFormula ? "'".$cell : $cell;
    }
}

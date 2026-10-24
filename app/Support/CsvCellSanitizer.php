<?php

namespace App\Support;

/**
 * Menetralkan nilai sel CSV/XLSX agar tidak dieksekusi sebagai formula oleh spreadsheet.
 */
final class CsvCellSanitizer
{
    private const PROBE_TRIM = " \n\x0B\x0C\0\xA0";

    private const NEGATIVE_NUMBER_PATTERN = '/^-\d+(?:[.,]\d+)?$/';

    /**
     * Memberi awalan apostrof pada sel yang berpotensi formula; angka negatif murni dan sel lain dibiarkan.
     */
    public static function sanitize(mixed $value): string
    {
        $cell = (string) $value;
        $probe = ltrim($cell, self::PROBE_TRIM);

        if (preg_match(self::NEGATIVE_NUMBER_PATTERN, $probe) === 1) {
            return $cell;
        }

        $isFormula = str_starts_with($probe, '=')
            || str_starts_with($probe, '+')
            || str_starts_with($probe, '-')
            || str_starts_with($probe, '@')
            || str_starts_with($probe, "\t")
            || str_starts_with($probe, "\r");

        return $isFormula ? "'".$cell : $cell;
    }
}

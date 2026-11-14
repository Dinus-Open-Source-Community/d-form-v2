<?php

namespace App\Support;

/** Menetralkan nilai sel CSV/XLSX agar tidak dieksekusi sebagai formula oleh spreadsheet. */
final class CsvCellSanitizer
{
    /** Byte-wide ASCII whitespace kept for the invalid-UTF-8 fallback path. */
    private const ASCII_TRIM = " \n\r\t\x0B\x0C\0\xA0";

    /** Detect leading characters that spreadsheet apps may treat as formula whitespace. */
    private const PROBE_TRIM_PATTERN = '/\G[\x00-\x20\x{A0}\x{1680}\x{2000}-\x{200D}\x{2028}\x{2029}\x{202F}\x{205F}\x{2060}\x{3000}\x{FEFF}]+/u';

    /** Probe only a bounded prefix so sanitization remains constant-cost for large cells. */
    private const PROBE_BYTE_CAP = 4096;

    private const FORMULA_TRIGGERS = '=+-@';

    private const NEGATIVE_NUMBER_PATTERN = '/^-\d+(?:[.,]\d+)?$/';

    /** Memberi awalan apostrof pada sel yang berpotensi formula; angka negatif murni dan sel lain dibiarkan. */
    public static function sanitize(mixed $value): string
    {
        $cell = (string) $value;
        $probe = self::formulaProbe($cell);

        if ($probe === null) {
            // Unvalidated leading bytes: fail closed with the apostrophe prefix.
            return "'".$cell;
        }

        if ($probe === '') {
            return $cell;
        }

        if (preg_match(self::NEGATIVE_NUMBER_PATTERN, $probe) === 1) {
            return $cell;
        }

        return str_contains(self::FORMULA_TRIGGERS, $probe[0]) ? "'".$cell : $cell;
    }

    /** Bounded probe after leading whitespace; {@see PROBE_BYTE_CAP} keeps invalid UTF-8 checks fail-closed and bounded.
     * @return string|null Probe starting at the first non-whitespace byte, or null
     */
    private static function formulaProbe(string $cell): ?string
    {
        $window = strlen($cell) > self::PROBE_BYTE_CAP
            ? mb_strcut($cell, 0, self::PROBE_BYTE_CAP, 'UTF-8')
            : $cell;

        $matched = preg_match(self::PROBE_TRIM_PATTERN, $window, $matches);

        if ($matched === 1) {
            return substr($cell, strlen($matches[0]));
        }

        if ($matched === 0) {
            return $cell;
        }

        // Invalid UTF-8 in the window. Strip the ASCII prefix with an
        // encoding-independent scan and fail closed when the remainder cannot be
        // proven safe: any non-ASCII first byte may be a truncated formula trigger.
        $asciiProbe = substr($window, strspn($window, self::ASCII_TRIM));

        if ($asciiProbe === '' || ord($asciiProbe[0]) < 0x80) {
            return $asciiProbe;
        }

        return null;
    }
}

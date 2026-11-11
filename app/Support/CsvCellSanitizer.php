<?php

namespace App\Support;

/**
 * Menetralkan nilai sel CSV/XLSX agar tidak dieksekusi sebagai formula oleh spreadsheet.
 */
final class CsvCellSanitizer
{
    /**
     * Byte-wide ASCII whitespace kept for the invalid-UTF-8 fallback path. `\xA0` is
     * the legacy single-byte NBSP; the UTF-8 form is covered by the pattern below.
     */
    private const ASCII_TRIM = " \n\r\t\x0B\x0C\0\xA0";

    /**
     * Characters a spreadsheet may ignore before reading the next character as a
     * formula trigger: ASCII C0 controls and space, NBSP, OGHAM SPACE MARK,
     * EN QUAD..ZERO WIDTH JOINER, LINE/PARAGRAPH SEPARATOR, NARROW NBSP, MEDIUM
     * MATHEMATICAL SPACE, WORD JOINER, IDEOGRAPHIC SPACE, and ZWNBSP (BOM).
     *
     * One combined class so the whole leading run is consumed by a single anchored
     * match instead of one `preg_match` per whitespace run.
     */
    private const PROBE_TRIM_PATTERN = '/\G[\x00-\x20\x{A0}\x{1680}\x{2000}-\x{200D}\x{2028}\x{2029}\x{202F}\x{205F}\x{2060}\x{3000}\x{FEFF}]+/u';

    /**
     * Only the leading bytes are probed: no spreadsheet trims more than a few KiB of
     * whitespace before evaluating a cell, so a bounded window keeps the cost O(1)
     * regardless of how much attacker-controlled whitespace precedes the value.
     */
    private const PROBE_BYTE_CAP = 4096;

    private const FORMULA_TRIGGERS = '=+-@';

    private const NEGATIVE_NUMBER_PATTERN = '/^-\d+(?:[.,]\d+)?$/';

    /**
     * Memberi awalan apostrof pada sel yang berpotensi formula; angka negatif murni dan sel lain dibiarkan.
     */
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

    /**
     * Bounded probe for a formula trigger after leading whitespace.
     *
     * The subject is capped at {@see PROBE_BYTE_CAP} bytes and trimmed by a single
     * anchored match, so the cost stays constant no matter how long the leading run
     * is. The cut uses `mb_strcut()` so a valid multibyte character is never split
     * across the window boundary (that would make PCRE reject the whole subject and
     * mislabel a benign cell as invalid UTF-8).
     *
     * @return string|null Probe starting at the first non-whitespace byte, or null
     *                     when those bytes are not valid UTF-8 (caller fails closed).
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

<?php

namespace Tests\Unit\Support;

use App\Support\CsvCellSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CsvCellSanitizerTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function dangerousCells(): array
    {
        return [
            'equals' => ["=cmd|'/C calc'!A1", "'=cmd|'/C calc'!A1"],
            'plus' => ['+1+1', "'+1+1"],
            'minus' => ['-1+1', "'-1+1"],
            'at' => ['@SUM(1+1)', "'@SUM(1+1)"],
            'leading spaces' => ['   =1+1', "'   =1+1"],
            'leading tab' => ["\t=1+1", "'\t=1+1"],
            'leading carriage return' => ["\r=1+1", "'\r=1+1"],
            'leading line feed' => ["\n=1+1", "'\n=1+1"],
            'leading non-breaking space' => ["\xA0=1+1", "'\xA0=1+1"],
            'leading UTF-8 non-breaking space' => ["\xC2\xA0=1+1", "'\xC2\xA0=1+1"],
            'mixed leading whitespace and UTF-8 non-breaking spaces' => [" \xC2\xA0 \xC2\xA0=1+1", "' \xC2\xA0 \xC2\xA0=1+1"],
            'leading narrow non-breaking space' => ["\xE2\x80\xAF=1+1", "'\xE2\x80\xAF=1+1"],
            'leading em space' => ["\xE2\x80\x83=1+1", "'\xE2\x80\x83=1+1"],
            'leading ideographic space' => ["\xE3\x80\x80=1+1", "'\xE3\x80\x80=1+1"],
            'leading zero width space' => ["\xE2\x80\x8B=1+1", "'\xE2\x80\x8B=1+1"],
            'leading word joiner' => ["\xE2\x81\xA0=1+1", "'\xE2\x81\xA0=1+1"],
            'leading byte order mark' => ["\xEF\xBB\xBF=1+1", "'\xEF\xBB\xBF=1+1"],
            'mixed ASCII and zero width space' => [" \xE2\x80\x8B \xC2\xA0 =1+1", "' \xE2\x80\x8B \xC2\xA0 =1+1"],
            'zero width space before minus formula' => ["\xE2\x80\x8B-1+1", "'\xE2\x80\x8B-1+1"],
        ];
    }

    #[DataProvider('dangerousCells')]
    public function test_prefixes_cells_that_can_be_read_as_formula(mixed $value, string $expected): void
    {
        self::assertSame($expected, CsvCellSanitizer::sanitize($value));
    }

    public function test_leaves_plain_cells_untouched(): void
    {
        self::assertSame('Peserta Biasa', CsvCellSanitizer::sanitize('Peserta Biasa'));
        self::assertSame('', CsvCellSanitizer::sanitize(null));
        self::assertSame('42', CsvCellSanitizer::sanitize(42));
        self::assertSame('-1', CsvCellSanitizer::sanitize('-1'));
        self::assertSame('-1500', CsvCellSanitizer::sanitize('-1500'));
        self::assertSame('-1.5', CsvCellSanitizer::sanitize('-1.5'));
        self::assertSame('-1,5', CsvCellSanitizer::sanitize('-1,5'));
        self::assertSame("\xE2\x80\xAF-1.5", CsvCellSanitizer::sanitize("\xE2\x80\xAF-1.5"));
        self::assertSame("\xE2\x80\x8B-1.5", CsvCellSanitizer::sanitize("\xE2\x80\x8B-1.5"));
        self::assertSame('   ', CsvCellSanitizer::sanitize('   '));
        self::assertSame('é=1', CsvCellSanitizer::sanitize('é=1'));
    }

    public function test_fails_closed_when_the_leading_bytes_are_not_valid_utf8(): void
    {
        foreach ([
            "\xFF=1+1",
            "\xC0\xAF=1+1",
            "\xAF=1+1",
            "\xFF-1",
        ] as $payload) {
            self::assertSame("'".$payload, CsvCellSanitizer::sanitize($payload));
        }
    }

    public function test_formula_after_whitespace_within_the_probe_cap_is_prefixed(): void
    {
        $prefix = str_repeat(" \xC2\xA0", 1_300); // 3900 bytes, below the 4096-byte cap.

        self::assertSame("'".$prefix.'=1+1', CsvCellSanitizer::sanitize($prefix.'=1+1'));
    }

    public function test_long_valid_utf8_cell_is_not_misread_at_the_probe_cap_boundary(): void
    {
        // The cap lands inside "é"; mb_strcut() must not split the character and
        // PCRE must not reject the window, otherwise this benign cell gains a '.
        $cell = str_repeat('a', 4_095)."é=1";

        self::assertSame($cell, CsvCellSanitizer::sanitize($cell));
    }

    /**
     * C1 regression: the probe must stay O(1) in the leading whitespace run. The
     * previous per-run preg_match loop took ~1.6 s for 120 kB; the capped single
     * match must stay far below the 10 ms budget.
     */
    public function test_large_alternating_whitespace_prefix_is_probed_in_bounded_time(): void
    {
        $prefix = str_repeat(" \xC2\xA0", 40_000); // 120 kB of alternating space/NBSP.
        $cell = $prefix.'=1+1';

        $startedAt = hrtime(true);
        $sanitized = CsvCellSanitizer::sanitize($cell);
        $elapsedMs = (hrtime(true) - $startedAt) / 1e6;

        // Beyond the 4 KiB probe cap no spreadsheet still trims the whitespace, so
        // the cell is returned unchanged (same output as before for the mixed cases
        // covered by the data provider).
        self::assertSame($cell, $sanitized);
        self::assertLessThan(10.0, $elapsedMs, sprintf('sanitize() took %.3f ms', $elapsedMs));
    }
}

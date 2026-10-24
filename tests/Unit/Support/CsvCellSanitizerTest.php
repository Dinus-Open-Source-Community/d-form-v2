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
    }
}

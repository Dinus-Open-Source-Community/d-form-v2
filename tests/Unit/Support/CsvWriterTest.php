<?php

namespace Tests\Unit\Support;

use App\Support\CsvWriter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CsvWriterTest extends TestCase
{
    public function test_writes_normal_values_without_changing_csv_output(): void
    {
        self::assertSame("name,Alice,-2\n", $this->writeRow(['name', 'Alice', -2]));
    }

    public function test_backslash_quote_payload_stays_in_one_sanitized_cell(): void
    {
        $payload = '\\\\",=1+1';
        $csv = $this->writeRow([$payload, '=SUM(1,2)']);
        $cells = str_getcsv($csv, ',', '"', '');

        self::assertCount(2, $cells);
        self::assertSame($payload, $cells[0]);
        self::assertSame("'=SUM(1,2)", $cells[1]);
        self::assertFalse(str_starts_with($cells[0], '='));
        self::assertFalse(str_starts_with($cells[1], '='));
    }

    public function test_sanitizes_formula_after_utf8_non_breaking_space(): void
    {
        $csv = $this->writeRow(["\xC2\xA0=1+1"]);

        self::assertSame(["'\xC2\xA0=1+1"], str_getcsv($csv, ',', '"', ''));
    }

    public function test_neutralizes_formulas_hidden_behind_unicode_whitespace(): void
    {
        foreach (["\xC2\xA0=1+1", "\xE2\x80\x8B=1+1", "\xE2\x80\xAF+1+1", "\xE3\x80\x80@SUM(1)"] as $payload) {
            $cells = str_getcsv($this->writeRow([$payload]), ',', '"', '');

            self::assertSame(["'".$payload], $cells, 'Cell must be prefixed, keeping the original bytes.');
        }
    }

    public function test_quoted_and_multiline_cells_remain_rfc4180_parseable(): void
    {
        $fields = ['say "hi", then bye', "line one\nline two", "tab\tinside"];

        $cells = str_getcsv($this->writeRow($fields), ',', '"', '');

        self::assertSame($fields, $cells);
    }

    public function test_quote_prefixed_formula_payload_is_escaped_not_left_active(): void
    {
        $payload = '"=1+1';

        $csv = $this->writeRow([$payload]);
        $cells = str_getcsv($csv, ',', '"', '');

        self::assertCount(1, $cells);
        self::assertSame($payload, $cells[0]);
        self::assertFalse(str_starts_with($cells[0], '='));
    }

    public function test_writer_never_triggers_the_php_84_fputcsv_deprecation(): void
    {
        set_error_handler(static function (int $level, string $message): bool {
            throw new \ErrorException($message, 0, $level);
        });

        try {
            $this->writeRow(['name', '=SUM(1,2)', 'quote " inside']);
        } finally {
            restore_error_handler();
        }
    }

    public function test_app_code_uses_the_central_writer_for_all_fputcsv_calls(): void
    {
        $applicationPath = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'app';
        $writerPath = realpath($applicationPath.DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'CsvWriter.php');
        self::assertIsString($writerPath);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($applicationPath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || $file->getRealPath() === $writerPath) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression(
                '/\bfputcsv\s*\(/',
                $source,
                'Direct fputcsv call found in '.$file->getPathname(),
            );
        }
    }

    public function test_rejects_non_stream_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CsvWriter::writeRow(new \stdClass(), ['value']);
    }

    /**
     * @param  array<array-key, mixed>  $fields
     */
    private function writeRow(array $fields): string
    {
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);

        CsvWriter::writeRow($stream, $fields);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($csv);

        return $csv;
    }
}

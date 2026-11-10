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

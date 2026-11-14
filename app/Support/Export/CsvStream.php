<?php

namespace App\Support\Export;

use App\Support\CsvWriter;
use InvalidArgumentException;

/** CSV response body writer: UTF-8 BOM, one header row, then every data row through {@see CsvWriter}, so escaping and formula neutralization stay identical across every export. */
final class CsvStream
{
    /**
     *
     * Write BOM + header + rows into an already open stream.
     * @param  resource  $stream
     * @param  list<string>  $header
     * @param  iterable<int, list<mixed>>  $rows
     * @param  int  $dataRowsWritten  data rows written so far, header excluded
     * @return int number of data rows written, header excluded
     *
     */
    public static function write(mixed $stream, array $header, iterable $rows, int &$dataRowsWritten = 0): int
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('CsvStream requires an open stream.');
        }

        $dataRowsWritten = 0;

        fwrite($stream, "\xEF\xBB\xBF");
        CsvWriter::writeRow($stream, $header);

        foreach ($rows as $row) {
            CsvWriter::writeRow($stream, $row);
            ++$dataRowsWritten;
        }

        return $dataRowsWritten;
    }
}

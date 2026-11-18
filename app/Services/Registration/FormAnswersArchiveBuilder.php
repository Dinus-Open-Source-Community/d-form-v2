<?php

namespace App\Services\Registration;

use App\Exceptions\FormAnswersArchiveTooLargeException;
use App\Models\Form;
use App\Support\CsvWriter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Membangun ZIP jawaban form: satu `jawaban.csv` gabungan di root dan satu folder
 * per pengirim yang hanya berisi lampiran jawabannya. Arsip ditulis ke file
 * sementara agar respons bisa streaming tanpa menahan seluruh isi di memori.
 */
final class FormAnswersArchiveBuilder
{
    public function __construct(private readonly FormAnswersExportService $export)
    {
    }

    /**
     * @return array{path: string, filename: string, rows: int, files: int, bytes: int}
     */
    public function build(Form $form): array
    {
        $fields = $this->export->fields($form);
        $maxFiles = (int) config('events.forms.answers_zip_max_files', 2000);
        $maxBytes = (int) config('events.forms.answers_zip_max_bytes', 536870912);

        $path = tempnam(sys_get_temp_dir(), 'dform-answers-');
        $csvPath = tempnam(sys_get_temp_dir(), 'dform-answers-csv-');
        if ($path === false || $csvPath === false) {
            @unlink($path);
            @unlink($csvPath);

            throw new RuntimeException('Unable to allocate a temporary archive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            @unlink($csvPath);

            throw new RuntimeException('Unable to create the form answers archive.');
        }

        $csv = fopen($csvPath, 'wb');
        if ($csv === false) {
            $zip->close();
            @unlink($path);
            @unlink($csvPath);

            throw new RuntimeException('Unable to create the combined answers CSV.');
        }

        $rows = 0;
        $files = 0;
        $bytes = 0;
        $accepted = false;

        try {
            CsvWriter::writeRow($csv, $this->export->csvHeader($fields));

            $folderNames = [];
            /** @var array<string, array<string, true>> $usedFiles */
            $usedFiles = [];

            foreach ($this->export->query($form)->lazy(200) as $answer) {
                $folder = $this->uniqueName(
                    $this->export->folderName($answer, $rows, $fields),
                    $folderNames,
                );

                $zip->addEmptyDir($folder);
                CsvWriter::writeRow($csv, $this->export->csvRow($answer, $fields));

                foreach ($this->export->fileEntries($answer, $fields) as $entry) {
                    $size = $this->sizeOf($entry['disk'], $entry['path']);
                    if ($size === null) {
                        continue;
                    }

                    ++$files;
                    $bytes += $size;

                    if ($files > $maxFiles || $bytes > $maxBytes) {
                        throw new FormAnswersArchiveTooLargeException($files, $bytes);
                    }

                    $zip->addFile(
                        Storage::disk($entry['disk'])->path($entry['path']),
                        $folder.'/'.$this->uniqueFile($entry['filename'], $folder, $usedFiles),
                    );
                }

                ++$rows;
            }

            fclose($csv);
            $csv = null;

            if (! $zip->addFile($csvPath, 'jawaban.csv')) {
                throw new RuntimeException('Unable to add the combined answers CSV to the archive.');
            }

            if ($zip->close() !== true) {
                throw new RuntimeException('Unable to finalize the form answers archive.');
            }

            $accepted = true;

            return [
                'path' => $path,
                'filename' => $this->archiveName($form),
                'rows' => $rows,
                'files' => $files,
                'bytes' => $bytes,
            ];
        } finally {
            if (is_resource($csv)) {
                fclose($csv);
            }

            @unlink($csvPath);

            if (! $accepted) {
                $zip->close();
                @unlink($path);
            }
        }
    }

    private function sizeOf(string $disk, string $path): ?int
    {
        try {
            $storage = Storage::disk($disk);

            return $storage->exists($path) ? (int) $storage->size($path) : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** Nama unik per folder; sufiks sebelum ekstensi agar file tidak saling menimpa.
     * @param  array<string, array<string, true>>  $usedFiles
     */
    private function uniqueFile(string $filename, string $folder, array &$usedFiles): string
    {
        $used = $usedFiles[$folder] ?? [];
        $candidate = $filename;
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $stem = $extension === '' ? $filename : substr($filename, 0, -(strlen($extension) + 1));
        $counter = 2;

        while (isset($used[$candidate])) {
            $candidate = $extension === ''
                ? $stem.' ('.$counter.')'
                : $stem.' ('.$counter.').'.$extension;
            ++$counter;
        }

        $used[$candidate] = true;
        $usedFiles[$folder] = $used;

        return $candidate;
    }

    /**
     * @param  array<string, true>  $used
     */
    private function uniqueName(string $name, array &$used): string
    {
        $candidate = $name;
        $counter = 2;

        while (isset($used[$candidate])) {
            $candidate = $name.' ('.$counter.')';
            ++$counter;
        }

        $used[$candidate] = true;

        return $candidate;
    }

    private function archiveName(Form $form): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($form->title)));
        $slug = trim(substr($slug, 0, 80), '-');

        return 'jawaban-'.($slug === '' ? $form->id : $slug).'.zip';
    }
}

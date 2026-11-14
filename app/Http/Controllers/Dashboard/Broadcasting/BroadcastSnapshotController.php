<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\GenerateSnapshotRequest;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastSnapshotService;
use App\Support\CsvRows;
use Illuminate\Http\RedirectResponse;

class BroadcastSnapshotController extends Controller
{
    public function __invoke(
        GenerateSnapshotRequest $request,
        EmailBroadcast $broadcast,
        BroadcastSnapshotService $snapshots,
    ): RedirectResponse {
        $this->authorize('update', $broadcast);

        $snapshots->assertEditable($broadcast);

        $datasets = $request->input('datasets', []);
        $manual = $request->input('manual', []);
        $csvRows = $this->parseCsv($request->file('csv_file'));

        $invalidCsv = $csvRows['invalid'] ?? [];

        $summary = $snapshots->generate($broadcast, $datasets, array_merge($manual, $csvRows['valid'] ?? []));

        $message = 'Snapshot dibuat: '.$summary['total'].' recipient.';
        if ($summary['duplicates'] > 0) {
            $message .= ' '.$summary['duplicates'].' duplikat terdeteksi (tidak dihapus otomatis).';
        }
        if ($invalidCsv !== []) {
            $message .= ' '.count($invalidCsv).' baris CSV invalid dilewati.';
        }

        return redirect()->back()->with('toast', ['message' => $message, 'type' => $summary['duplicates'] > 0 ? 'warning' : 'success']);
    }

    /**
     *
     * Parse CSV snapshot dengan guard {@see CsvRows}: batas baris/panjang baris dan penolakan baris biner; ukuran file dibatasi `UploadPurpose::DatasetCsv`.
     * @return array{valid:array<int,array{name:string|null,email:string}>, invalid:array<int,string>}
     *
     */
    private function parseCsv(?\Illuminate\Http\UploadedFile $file): array
    {
        if ($file === null) {
            return ['valid' => [], 'invalid' => []];
        }

        $valid = [];
        $invalid = [];
        $emailColumn = 0;
        $nameColumn = null;
        $headerChecked = false;

        CsvRows::each($file, function (array $row, int $index) use (&$valid, &$invalid, &$emailColumn, &$nameColumn, &$headerChecked): void {
            if ($index === 0 && ! $headerChecked) {
                $headerChecked = true;
                $lower = array_map(fn ($cell) => strtolower(trim((string) $cell)), $row);

                if (in_array('email', $lower, true)) {
                    $emailColumn = (int) array_search('email', $lower, true);
                    $nameIndex = array_search('name', $lower, true);
                    $nameColumn = is_int($nameIndex) ? $nameIndex : null;

                    return;
                }
            }

            $email = trim((string) ($row[$emailColumn] ?? ''));
            $name = $nameColumn !== null ? trim((string) ($row[$nameColumn] ?? '')) : null;

            // Dukung "name,email" tanpa header bila kolom 1 adalah email valid.
            if ($nameColumn === null && $index > 0 && count($row) >= 2) {
                $maybeEmail = trim((string) ($row[1] ?? ''));
                if (filter_var($maybeEmail, FILTER_VALIDATE_EMAIL) !== false) {
                    $name = trim((string) ($row[0] ?? ''));
                    $email = $maybeEmail;
                }
            }

            $this->pushCsvRow($email, $name, $valid, $invalid);
        });

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /**
     *
     * @param  array<int,array{name:string|null,email:string}>  $valid
     * @param  array<int,string>  $invalid
     *
     */
    private function pushCsvRow(string $email, ?string $name, array &$valid, array &$invalid): void
    {
        $email = strtolower(trim($email));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            if ($email !== '') {
                $invalid[] = $email;
            }

            return;
        }

        $valid[] = ['name' => $name !== '' ? $name : null, 'email' => $email];
    }
}

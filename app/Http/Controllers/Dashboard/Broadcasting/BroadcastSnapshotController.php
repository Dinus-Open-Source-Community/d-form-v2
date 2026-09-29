<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\GenerateSnapshotRequest;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastSnapshotService;
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
     * @return array{valid:array<int,array{name:string|null,email:string}>, invalid:array<int,string>}
     */
    private function parseCsv(?\Illuminate\Http\UploadedFile $file): array
    {
        if ($file === null) {
            return ['valid' => [], 'invalid' => []];
        }

        $valid = [];
        $invalid = [];

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return ['valid' => [], 'invalid' => []];
        }

        $header = fgetcsv($handle);
        $hasName = $header !== false && in_array('name', array_map('strtolower', array_map('trim', $header)), true);
        $emailIdx = 0;
        $nameIdx = null;

        if ($header !== false) {
            $lower = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
            if (in_array('email', $lower, true)) {
                $emailIdx = array_search('email', $lower, true);
                $nameIdx = array_search('name', $lower, true);
                $nameIdx = $nameIdx === false ? null : $nameIdx;
            } else {
                // Tanpa header: baris pertama adalah data.
                $this->pushCsvRow($header[$emailIdx] ?? '', $nameIdx !== null ? ($header[$nameIdx] ?? null) : null, $valid, $invalid);
            }
        }

        while (($row = fgetcsv($handle)) !== false) {
            $email = trim((string) ($row[$emailIdx] ?? ''));
            $name = $nameIdx !== null ? trim((string) ($row[$nameIdx] ?? '')) : null;
            // Format minimum "email" saja; jika header tidak ada name, kolom 0 = email.
            if (! $hasName && count($row) >= 2 && $emailIdx === 0) {
                // dukung "name,email" tanpa header? perlakukan kolom 0 sbg name bila 2 kolom dan kolom 1 valid email
                $maybeEmail = trim((string) ($row[1] ?? ''));
                if (filter_var($maybeEmail, FILTER_VALIDATE_EMAIL) !== false) {
                    $name = trim((string) ($row[0] ?? ''));
                    $email = $maybeEmail;
                }
            }
            $this->pushCsvRow($email, $name, $valid, $invalid);
        }

        fclose($handle);

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * @param  array<int,array{name:string|null,email:string}>  $valid
     * @param  array<int,string>  $invalid
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

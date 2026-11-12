<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\StoreCustomDatasetRequest;
use App\Models\EmailDataset;
use App\Services\Broadcasting\BroadcastDatasetResolver;
use App\Support\CsvRows;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastDatasetController extends Controller
{
    private const PREVIEW_SAMPLE_SIZE = 5;

    public function index(): Response
    {
        $this->authorize('viewAny', \App\Models\EmailBroadcast::class);

        $datasets = EmailDataset::query()
            ->withCount('recipients')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return Inertia::render('Dashboard/Broadcasts/Datasets', [
            'datasets' => $datasets,
        ]);
    }

    public function store(StoreCustomDatasetRequest $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\EmailBroadcast::class);

        $dataset = EmailDataset::query()->create([
            'name' => $request->string('name')->toString(),
            'source_type' => 'custom',
            'created_by' => $request->user()->id,
        ]);

        $rows = $request->input('manual', []);

        if ($request->hasFile('csv_file')) {
            $rows = array_merge($rows, $this->parseCsvEmails($request->file('csv_file'))['valid']);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $dataset->recipients()->insert(array_map(fn ($r) => [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'dataset_id' => $dataset->id,
                'name' => $r['name'] ?? null,
                'email' => strtolower(trim((string) ($r['email'] ?? ''))),
                'created_at' => now(),
                'updated_at' => now(),
            ], array_filter($chunk, fn ($r) => filter_var(strtolower(trim((string) ($r['email'] ?? ''))), FILTER_VALIDATE_EMAIL) !== false)));
        }

        return redirect()->back()->with('toast', ['message' => 'Custom dataset dibuat.', 'type' => 'success']);
    }

    public function preview(Request $request, BroadcastDatasetResolver $resolver): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', \App\Models\EmailBroadcast::class);

        $type = $request->string('type')->toString();
        $eventId = $request->input('event_id');
        $periodId = $request->input('period_id');
        $datasetId = $request->input('dataset_id');

        $rows = match ($type) {
            'event_participants' => $resolver->streamEventParticipants($eventId ?: null),
            'recruitment_applicants' => $resolver->streamRecruitmentApplicants($periodId ?: null),
            'users' => $resolver->streamUsers(),
            'custom' => $datasetId ? $resolver->streamCustomDataset((string) $datasetId) : $this->emptyRows(),
            default => $this->emptyRows(),
        };

        // Single streaming pass: count everything, keep only the preview sample.
        $total = 0;
        $sample = [];

        foreach ($rows as $row) {
            $total++;

            if (count($sample) < self::PREVIEW_SAMPLE_SIZE) {
                $sample[] = $row;
            }
        }

        return response()->json([
            'total' => $total,
            'sample' => $sample,
        ]);
    }

    /** @return \Generator<int, array{name:string|null, email:string}> */
    private function emptyRows(): \Generator
    {
        yield from [];
    }

    /**
     * Parse CSV baris demi baris (tanpa menampung seluruh file di memori).
     *
     * Ukuran file divalidasi `UploadPurpose::DatasetCsv` (2 MB); batas jumlah
     * baris, panjang baris, dan penolakan baris biner ditangani {@see CsvRows}.
     *
     * @return array{valid:array<int,array{name:string|null,email:string}>}
     */
    private function parseCsvEmails(?\Illuminate\Http\UploadedFile $file): array
    {
        if ($file === null) {
            return ['valid' => []];
        }

        $valid = [];
        $emailColumn = 0;
        $nameColumn = null;
        $headerChecked = false;

        CsvRows::each($file, function (array $row, int $index) use (&$valid, &$emailColumn, &$nameColumn, &$headerChecked): void {
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

            $this->appendEmailRow($row, $emailColumn, $nameColumn, $valid);
        });

        return ['valid' => $valid];
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<int, array{name:string|null,email:string}>  $valid
     */
    private function appendEmailRow(array $row, int $emailColumn, ?int $nameColumn, array &$valid): void
    {
        $email = strtolower(trim((string) ($row[$emailColumn] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $valid[] = [
            'name' => $nameColumn !== null ? (trim((string) ($row[$nameColumn] ?? '')) ?: null) : null,
            'email' => $email,
        ];
    }
}

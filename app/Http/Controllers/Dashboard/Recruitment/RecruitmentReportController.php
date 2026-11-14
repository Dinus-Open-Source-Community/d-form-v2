<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ShowRecruitmentReportRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentActivityLogger;
use App\Services\Recruitment\RecruitmentReportService;
use App\Support\CsvWriter;
use App\Support\Export\CsvStream;
use App\Support\Export\KeysetPage;
use App\Support\Export\KeysetPageIterator;
use Illuminate\Database\Query\JoinClause;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecruitmentReportController extends Controller
{
    /** Kontrak kolom roster pelamar; urutan tidak boleh berubah. */
    private const APPLICANT_HEADER = [
        'registration_number',
        'full_name',
        'nim',
        'semester',
        'primary_division',
        'secondary_division',
        'stage',
        'result',
        'submitted_at',
    ];

    /** Satu halaman roster; puncak memori dibatasi ke satu halaman model. */
    private const APPLICANT_PAGE_SIZE = 500;

    public function __construct(
        private readonly RecruitmentReportService $reportService,
    ) {
    }

    public function exportFunnel(ShowRecruitmentReportRequest $request): StreamedResponse
    {
        abort_unless($request->user()?->can('recruitment.reports.export'), 403);

        $period = $this->resolveExportPeriod($request->validated('period_id'));
        $this->authorize('view', $period);

        $funnel = $this->reportService->buildFunnel($period->id);
        $fileName = 'oprec-funnel-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($funnel): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            CsvWriter::writeRow($out, ['stage', 'label', 'count']);

            foreach ($funnel as $row) {
                CsvWriter::writeRow($out, [$row['stage'], $row['label'], $row['count']]);
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportApplicants(
        ShowRecruitmentReportRequest $request,
        RecruitmentActivityLogger $activityLogger,
    ): StreamedResponse {
        $actor = $request->user();

        if (! ($actor instanceof User) || ! $actor->can('recruitment.reports.export')) {
            abort(403);
        }

        $period = $this->resolveExportPeriod($request->validated('period_id'));
        $this->authorize('view', $period);

        // PII roster: izin ekspor aplikasi wajib terpisah dari izin laporan.
        abort_unless($actor->can('recruitment.applications.export'), 403);

        $resolvedPeriodId = $period->id;
        $fileName = 'oprec-applicants-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($resolvedPeriodId, $activityLogger, $actor): void {
            $out = fopen('php://output', 'wb');
            $rowsGenerated = 0;

            try {
                if ($out === false) {
                    return;
                }

                CsvStream::write(
                    $out,
                    self::APPLICANT_HEADER,
                    $this->applicantRows($resolvedPeriodId),
                    $rowsGenerated,
                );
            } finally {
                if ($out !== false) {
                    fclose($out);
                }

                // Audit tetap tercatat saat generator/DB gagal atau klien memutus
                // stream: `$rowsGenerated` berisi baris yang benar-benar sudah ditulis.
                $activityLogger->recordExport($actor, $resolvedPeriodId, 'applicants', $rowsGenerated);
            }
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     *
     * Baris roster pelamar per periode, keyset `registration_number` (unik global).
     * @return \Generator<int, list<string>>
     *
     */
    private function applicantRows(string $periodId): \Generator
    {
        $applications = new KeysetPageIterator(
            RecruitmentApplication::query()
                ->leftJoin('recruitment_divisions as primary_division', function (JoinClause $join): void {
                    $join->on('primary_division.id', '=', 'recruitment_applications.primary_division_id')
                        ->whereNull('primary_division.deleted_at');
                })
                ->leftJoin('recruitment_divisions as secondary_division', function (JoinClause $join): void {
                    $join->on('secondary_division.id', '=', 'recruitment_applications.secondary_division_id')
                        ->whereNull('secondary_division.deleted_at');
                })
                ->where('recruitment_applications.recruitment_period_id', $periodId)
                ->select([
                    'recruitment_applications.registration_number',
                    'recruitment_applications.full_name',
                    'recruitment_applications.nim',
                    'recruitment_applications.semester',
                    'primary_division.name as primary_division_name',
                    'secondary_division.name as secondary_division_name',
                    'recruitment_applications.stage',
                    'recruitment_applications.result',
                    'recruitment_applications.submitted_at',
                ])
        );

        $page = new KeysetPage(
            'recruitment_applications.registration_number',
            'recruitment_applications.registration_number',
            pageSize: self::APPLICANT_PAGE_SIZE,
        );

        foreach ($applications->iterate($page) as $application) {
            yield [
                $application->registration_number,
                $application->full_name,
                $application->nim,
                $application->semester,
                $application->primary_division_name ?? '',
                $application->secondary_division_name ?? '',
                $application->stage->value,
                $application->result->value,
                $application->submitted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
            ];
        }
    }

    /** Resolve the export period and return 404 for a missing or invalid period. */
    private function resolveExportPeriod(?string $periodId): RecruitmentPeriod
    {
        $period = $this->reportService->resolvePeriodModel($periodId);

        if ($period === null) {
            abort(404);
        }

        return $period;
    }
}

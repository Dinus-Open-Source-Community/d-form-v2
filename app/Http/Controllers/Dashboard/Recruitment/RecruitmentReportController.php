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
use Illuminate\Database\Query\JoinClause;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecruitmentReportController extends Controller
{
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
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            CsvWriter::writeRow($out, [
                'registration_number',
                'full_name',
                'nim',
                'semester',
                'primary_division',
                'secondary_division',
                'stage',
                'result',
                'submitted_at',
            ]);

            $rowsGenerated = 0;

            // Keyset loop (bukan OFFSET, bukan cursor()): PDO MySQL default tetap
            // mem-buffer seluruh result set, sehingga cursor() pada 1.000.000 baris
            // menambah ~116 MB; halaman 500 baris membatasi puncak memori ke satu
            // halaman. `registration_number` unik, jadi `>` terhadap nilai terakhir
            // tidak melewatkan atau menggandakan baris.
            $pageSize = 500;
            $lastRegistrationNumber = '';

            while (true) {
                $applications = RecruitmentApplication::query()
                    ->leftJoin('recruitment_divisions as primary_division', function (JoinClause $join): void {
                        $join->on('primary_division.id', '=', 'recruitment_applications.primary_division_id')
                            ->whereNull('primary_division.deleted_at');
                    })
                    ->leftJoin('recruitment_divisions as secondary_division', function (JoinClause $join): void {
                        $join->on('secondary_division.id', '=', 'recruitment_applications.secondary_division_id')
                            ->whereNull('secondary_division.deleted_at');
                    })
                    ->where('recruitment_applications.recruitment_period_id', $resolvedPeriodId)
                    ->where('recruitment_applications.registration_number', '>', $lastRegistrationNumber)
                    ->orderBy('recruitment_applications.registration_number')
                    ->limit($pageSize)
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
                    ->get();

                if ($applications->isEmpty()) {
                    break;
                }

                foreach ($applications as $application) {
                    CsvWriter::writeRow($out, [
                        $application->registration_number,
                        $application->full_name,
                        $application->nim,
                        $application->semester,
                        $application->primary_division_name ?? '',
                        $application->secondary_division_name ?? '',
                        $application->stage->value,
                        $application->result->value,
                        $application->submitted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
                    ]);
                    $rowsGenerated++;
                }

                $lastRegistrationNumber = (string) $applications->last()?->registration_number;

                if ($applications->count() < $pageSize) {
                    break;
                }
            }

            fclose($out);
            $activityLogger->recordExport($actor, $resolvedPeriodId, 'applicants', $rowsGenerated);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Resolve the export period and fail closed: an unknown `period_id` or no
     * period at all is 404, never an empty CSV (an empty body would hide both
     * "wrong id" and "no permission" from the caller).
     */
    private function resolveExportPeriod(?string $periodId): RecruitmentPeriod
    {
        $period = $this->reportService->resolvePeriodModel($periodId);

        if ($period === null) {
            abort(404);
        }

        return $period;
    }
}

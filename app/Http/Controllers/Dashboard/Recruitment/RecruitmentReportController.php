<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ShowRecruitmentReportRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\RecruitmentReportService;
use App\Support\CsvCellSanitizer;
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

        $periodId = $request->validated('period_id');
        $funnel = $this->reportService->build($periodId)['funnel'];
        $fileName = 'oprec-funnel-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($funnel): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(CsvCellSanitizer::sanitize(...), ['stage', 'label', 'count']));

            foreach ($funnel as $row) {
                fputcsv($out, array_map(CsvCellSanitizer::sanitize(...), [$row['stage'], $row['label'], $row['count']]));
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportApplicants(ShowRecruitmentReportRequest $request): StreamedResponse
    {
        abort_unless($request->user()?->can('recruitment.reports.export'), 403);

        $periodId = $request->validated('period_id');
        $report = $this->reportService->build($periodId);
        $resolvedPeriodId = $report['period']['id'] ?? null;
        $fileName = 'oprec-applicants-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($resolvedPeriodId): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(CsvCellSanitizer::sanitize(...), [
                'registration_number',
                'full_name',
                'nim',
                'semester',
                'primary_division',
                'secondary_division',
                'stage',
                'result',
                'submitted_at',
            ]));

            if ($resolvedPeriodId === null) {
                fclose($out);

                return;
            }

            RecruitmentApplication::query()
                ->where('recruitment_period_id', $resolvedPeriodId)
                ->with(['primaryDivision:id,name', 'secondaryDivision:id,name'])
                ->orderBy('registration_number')
                ->chunk(200, function ($applications) use ($out): void {
                    foreach ($applications as $application) {
                        fputcsv($out, array_map(CsvCellSanitizer::sanitize(...), [
                            $application->registration_number,
                            $application->full_name,
                            $application->nim,
                            $application->semester,
                            $application->primaryDivision?->name ?? '',
                            $application->secondaryDivision?->name ?? '',
                            $application->stage->value,
                            $application->result->value,
                            $application->submitted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
                        ]));
                    }
                });

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

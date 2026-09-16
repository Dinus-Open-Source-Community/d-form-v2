<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ShowRecruitmentReportRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\RecruitmentReportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecruitmentReportController extends Controller
{
    public function __construct(
        private readonly RecruitmentReportService $reportService,
    ) {
    }

    public function index(ShowRecruitmentReportRequest $request): Response
    {
        abort_unless($request->user()?->can('recruitment.reports.view'), 403);

        $periodId = $request->validated('period_id');

        return Inertia::render('Dashboard/Recruitment/Reports/Index', [
            'report' => $this->reportService->build($periodId),
            'periodOptions' => $this->reportService->periodOptions(),
            'query' => ['period_id' => $periodId],
            'exportUrls' => [
                'funnel' => route('dashboard.recruitment.reports.export.funnel', ['period_id' => $periodId]),
                'applicants' => route('dashboard.recruitment.reports.export.applicants', ['period_id' => $periodId]),
            ],
        ]);
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
            fputcsv($out, array_map(self::sanitizeCsvCell(...), ['stage', 'label', 'count']));

            foreach ($funnel as $row) {
                fputcsv($out, array_map(self::sanitizeCsvCell(...), [$row['stage'], $row['label'], $row['count']]));
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
            fputcsv($out, array_map(self::sanitizeCsvCell(...), [
                'registration_number',
                'full_name',
                'nim',
                'semester',
                'primary_division',
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
                ->with(['primaryDivision:id,name'])
                ->orderBy('registration_number')
                ->chunk(200, function ($applications) use ($out): void {
                    foreach ($applications as $application) {
                        fputcsv($out, array_map(self::sanitizeCsvCell(...), [
                            $application->registration_number,
                            $application->full_name,
                            $application->nim,
                            $application->semester,
                            $application->primaryDivision?->name ?? '',
                            $application->stage->value,
                            $application->result->value,
                            $application->submitted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
                        ]));
                    }
                });

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function sanitizeCsvCell(mixed $value): string
    {
        $cell = (string) $value;

        return match (true) {
            str_starts_with($cell, '='),
            str_starts_with($cell, '+'),
            str_starts_with($cell, '-'),
            str_starts_with($cell, '@'),
            str_starts_with($cell, "\t"),
            str_starts_with($cell, "\r") => "'".$cell,
            default => $cell,
        };
    }
}

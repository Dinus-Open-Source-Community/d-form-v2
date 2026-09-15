<?php

namespace App\Http\Controllers\Dashboard\Scan;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Services\Registration\BundleGuestDisplayNameResolver;
use App\Services\Registration\FormAnswerRecipientResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GlobalScanExportController extends Controller
{
    private const EVENT_HEADER = [
        'scanned_at',
        'attendee_name',
        'attendee_email',
        'form_title',
        'registration_code',
        'scanned_by_name',
        'scanned_by_email',
    ];

    private const OPREC_HEADER = [
        'checked_in_at',
        'applicant_name',
        'registration_number',
        'nim',
        'division',
        'session_date',
        'method',
        'checked_in_by',
    ];

    public function __construct(
        private readonly BundleGuestDisplayNameResolver $displayNameResolver,
        private readonly FormAnswerRecipientResolver $recipientResolver,
    ) {
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ($user->can('events.list') || $user->can('recruitment.attendance.scan')),
            403
        );

        $kind = (string) $request->query('kind', '');
        $format = strtolower((string) $request->query('format', 'csv'));
        $target = (string) $request->query('target', '');

        abort_unless(in_array($format, ['csv', 'xlsx'], true), 422);

        return match ($kind) {
            'event' => $this->exportEvent($request, $target, $format),
            'oprec' => $this->exportOprec($request, $target, $format),
            default => abort(422),
        };
    }

    private function exportEvent(Request $request, string $target, string $format): StreamedResponse
    {
        $event = Event::query()->find($target);
        abort_if($event === null, 404);

        // Mirror GlobalScanController::store event branch: same row-level check.
        abort_unless($request->user()?->can('update', $event) === true, 403);

        $slug = $this->slug($event->slug !== null && $event->slug !== '' ? $event->slug : $event->title);
        $fileName = 'attendance-'.$slug.'-'.now()->format('Ymd-His').'.'.$format;

        return response()->streamDownload(
            function () use ($event, $format): void {
                $this->write($format, self::EVENT_HEADER, $this->eventRows($event));
            },
            $fileName,
            $this->headers($format),
        );
    }

    private function exportOprec(Request $request, string $target, string $format): StreamedResponse
    {
        $session = RecruitmentInterviewSession::query()
            ->with('division:id,name')
            ->find($target);
        abort_if($session === null, 404);

        abort_unless($request->user()?->can('recruitment.attendance.scan') === true, 403);

        $division = $this->slug($session->division?->name ?? 'oprec');
        $sessionDate = $session->session_date?->format('Y-m-d') ?? 'no-date';
        $fileName = 'attendance-oprec-'.$division.'-'.$sessionDate.'-'.now()->format('Ymd-His').'.'.$format;

        return response()->streamDownload(
            function () use ($session, $format): void {
                $this->write($format, self::OPREC_HEADER, $this->oprecRows($session));
            },
            $fileName,
            $this->headers($format),
        );
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<int, list<string|int|null>>  $rows
     */
    private function write(string $format, array $header, iterable $rows): void
    {
        if ($format === 'xlsx') {
            $writer = new XlsxWriter();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($header));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();

            return;
        }

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            return;
        }

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, $row, ',', '"', '');
        }

        fclose($out);
    }

    /**
     * @return \Generator<int, list<string|int|null>>
     */
    private function eventRows(Event $event): \Generator
    {
        $attendances = $event->attendances()
            ->with(['formAnswer.user:id,name,email', 'formAnswer.form:id,title', 'scannedBy:id,name,email'])
            ->orderBy('scanned_at')
            ->lazy();

        foreach ($attendances as $row) {
            $submission = $row->formAnswer;
            $scanner = $row->scannedBy;

            yield [
                $row->scanned_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
                $submission !== null ? $this->displayNameResolver->resolve($submission) : '',
                $submission !== null ? ($this->recipientResolver->email($submission) ?? '') : '',
                $submission?->form?->title ?? '',
                $submission?->registration_code ?? '',
                $scanner?->name ?? '',
                $scanner?->email ?? '',
            ];
        }
    }

    /**
     * @return \Generator<int, list<string|int|null>>
     */
    private function oprecRows(RecruitmentInterviewSession $session): \Generator
    {
        $attendances = $session->attendances()
            ->with(['application.primaryDivision:id,name', 'checkedInByUser:id,name'])
            ->orderBy('checked_in_at')
            ->lazy();

        foreach ($attendances as $row) {
            $application = $row->application;
            $division = $session->division?->name ?? $application?->primaryDivision?->name ?? '';

            yield [
                $row->checked_in_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
                $application?->full_name ?? '',
                $application?->registration_number ?? '',
                $application?->nim ?? '',
                $division,
                $session->session_date?->format('Y-m-d') ?? '',
                $row->method?->value ?? '',
                $row->checkedInByUser?->name ?? '',
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $format): array
    {
        if ($format === 'xlsx') {
            return ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        }

        return ['Content-Type' => 'text/csv; charset=UTF-8'];
    }

    private function slug(string $value): string
    {
        $slug = Str::slug($value);

        return $slug !== '' ? $slug : 'attendance';
    }
}

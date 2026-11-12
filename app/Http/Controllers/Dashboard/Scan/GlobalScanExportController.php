<?php

namespace App\Http\Controllers\Dashboard\Scan;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Services\Registration\BundleGuestDisplayNameResolver;
use App\Services\Registration\FormAnswerRecipientResolver;
use App\Support\CsvCellSanitizer;
use App\Support\EventExportAuditLogger;
use App\Support\Export\CsvStream;
use App\Support\Export\KeysetPage;
use App\Support\Export\KeysetPageIterator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
        private readonly EventExportAuditLogger $auditLogger,
    ) {
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ($user->can('events.list') || $user->can('recruitment.attendance.scan')),
            403
        );

        $query = $request->query();
        $requestedFormat = $query['format'] ?? 'csv';

        if (is_string($requestedFormat)) {
            $query['format'] = strtolower($requestedFormat);
        }

        $parameters = Validator::make($query, [
            'kind' => ['required', 'string', 'in:event,oprec'],
            'format' => ['required', 'string', 'in:csv,xlsx'],
            'target' => ['required', 'string', 'uuid'],
        ]);

        if ($parameters->fails()) {
            return response()->json([
                'message' => 'Invalid export parameters.',
                'errors' => $parameters->errors(),
            ], 422);
        }

        $valid = $parameters->validated();
        $kind = (string) $valid['kind'];
        $format = (string) $valid['format'];
        $target = (string) $valid['target'];

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
        $actorId = $request->user()?->getAuthIdentifier();
        $exportType = 'scan_event_'.$format;

        return response()->streamDownload(
            function () use ($event, $format, $target, $actorId, $exportType): void {
                $rowCount = 0;

                try {
                    $this->write($format, self::EVENT_HEADER, $this->eventRows($event), $rowCount);
                } finally {
                    // PII-free trail, recorded even when the generator throws or the
                    // client disconnects mid-stream. The logger swallows its own
                    // failures so the download is never aborted by auditing.
                    $this->auditLogger->record($actorId, $target, $exportType, $rowCount);
                }
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
        $actorId = $request->user()?->getAuthIdentifier();
        $exportType = 'scan_oprec_'.$format;

        return response()->streamDownload(
            function () use ($session, $format, $target, $actorId, $exportType): void {
                $rowCount = 0;

                try {
                    $this->write($format, self::OPREC_HEADER, $this->oprecRows($session), $rowCount);
                } finally {
                    // OPREC export carries applicant names, NIM, and emails too, so it
                    // leaves the same PII-free trail keyed by interview session id.
                    $this->auditLogger->record($actorId, $target, $exportType, $rowCount);
                }
            },
            $fileName,
            $this->headers($format),
        );
    }

    /**
     * Write the body for the requested format and count the data rows actually
     * written, so the caller can audit partial output after a mid-stream failure.
     *
     * @param  list<string>  $header
     * @param  iterable<int, list<string|int|null>>  $rows
     * @param  int  $rowCount  data rows written, header excluded
     */
    private function write(string $format, array $header, iterable $rows, int &$rowCount): void
    {
        $rowCount = 0;

        if ($format === 'xlsx') {
            $writer = new XlsxWriter();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($header));

            foreach ($this->sanitizeRows($rows) as $row) {
                $writer->addRow(Row::fromValues($row));
                ++$rowCount;
            }

            $writer->close();

            return;
        }

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            return;
        }

        try {
            CsvStream::write($out, $header, $rows, $rowCount);
        } finally {
            fclose($out);
        }
    }

    /**
     * Menyaring setiap sel data sebelum ditulis ke CSV maupun XLSX.
     *
     * @param  iterable<int, list<string|int|null>>  $rows
     * @return \Generator<int, list<string>>
     */
    private function sanitizeRows(iterable $rows): \Generator
    {
        foreach ($rows as $row) {
            yield array_map(CsvCellSanitizer::sanitize(...), $row);
        }
    }

    /**
     * @return \Generator<int, list<string|int|null>>
     */
    private function eventRows(Event $event): \Generator
    {
        $query = $event->attendances()
            ->with(['formAnswer.user:id,name,email', 'formAnswer.form:id,title', 'scannedBy:id,name,email'])
            ->getQuery();

        $page = new KeysetPage('scanned_at', 'id', pageSize: $this->exportPageSize());

        foreach ((new KeysetPageIterator($query))->iterate($page) as $row) {
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
        $query = $session->attendances()
            ->with(['application.primaryDivision:id,name', 'checkedInByUser:id,name'])
            ->getQuery();

        $page = new KeysetPage('checked_in_at', 'id', pageSize: $this->exportPageSize());

        foreach ((new KeysetPageIterator($query))->iterate($page) as $row) {
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

    private function exportPageSize(): int
    {
        return max(1, min(5000, (int) config('scan.export_page_size', 1000)));
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

<?php

namespace App\Http\Controllers\Dashboard\Events\Exports;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Services\Registration\BundleGuestDisplayNameResolver;
use App\Services\Registration\FormAnswerRecipientResolver;
use App\Support\EventExportAuditLogger;
use App\Support\Export\CsvStream;
use App\Support\Export\KeysetPage;
use App\Support\Export\KeysetPageIterator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventAttendanceCsvExportController extends Controller
{
    /** Kolom CSV; urutan ini juga dikunci test byte-identity. */
    private const HEADER = [
        'scanned_at',
        'attendee_name',
        'attendee_email',
        'form_answer_id',
        'scanned_by_name',
        'scanned_by_email',
    ];

    /** Batas atas ukuran halaman: satu halaman memuat relasi attendee + scanner, jadi nilai env yang terlalu besar berakhir sebagai memory limit. */
    private const MAX_PAGE_SIZE = 2000;

    public function __invoke(
        Event $event,
        BundleGuestDisplayNameResolver $displayNameResolver,
        FormAnswerRecipientResolver $recipientResolver,
    ): StreamedResponse {
        $this->authorize('view', $event);

        $fileName = 'attendance-event-'.$event->id.'.csv';
        $actorId = auth()->id();
        $auditLogger = app(EventExportAuditLogger::class);

        return response()->streamDownload(function () use ($event, $displayNameResolver, $recipientResolver, $actorId, $auditLogger): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            $rowCount = 0;

            try {
                CsvStream::write(
                    $out,
                    self::HEADER,
                    $this->attendanceRows($event, $displayNameResolver, $recipientResolver),
                    $rowCount,
                );
            } finally {
                // Audit tetap tercatat walau generator/stream gagal di tengah
                // (mis. client abort): row_count = baris yang sempat ditulis.
                fclose($out);
                $auditLogger->record($actorId, (string) $event->getKey(), 'attendance', $rowCount);
            }
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Keyset uses stable non-null `scanned_at` plus unique `id`; neither value may change during export.
     * @return \Generator<int, list<mixed>>
     */
    protected function attendanceRows(
        Event $event,
        BundleGuestDisplayNameResolver $displayNameResolver,
        FormAnswerRecipientResolver $recipientResolver,
    ): \Generator {
        $query = $event->attendances()
            ->getQuery()
            ->with(['formAnswer.user:id,name,email', 'scannedBy:id,name,email']);

        $page = new KeysetPage('scanned_at', 'id', pageSize: self::resolvePageSize());

        /** @var EventAttendance $row */
        foreach ((new KeysetPageIterator($query))->iterate($page) as $row) {
            $submission = $row->formAnswer;
            $scanner = $row->scannedBy;

            yield [
                $row->scanned_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                $submission !== null ? $displayNameResolver->resolve($submission) : '',
                $submission !== null ? ($recipientResolver->email($submission) ?? '') : '',
                $row->form_answer_id,
                $scanner?->name ?? '',
                $scanner?->email ?? '',
            ];
        }
    }

    private static function resolvePageSize(): int
    {
        return min(self::MAX_PAGE_SIZE, max(1, (int) config('registration.attendance_export_page_size')));
    }
}

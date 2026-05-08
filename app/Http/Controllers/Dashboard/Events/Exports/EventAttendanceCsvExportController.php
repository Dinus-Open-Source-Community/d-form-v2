<?php

namespace App\Http\Controllers\Dashboard\Events\Exports;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventAttendanceCsvExportController extends Controller
{
    public function __invoke(Event $event): StreamedResponse
    {
        $this->authorize('view', $event);

        $fileName = 'attendance-event-'.$event->id.'.csv';

        return response()->streamDownload(function () use ($event): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'scanned_at',
                'attendee_name',
                'attendee_email',
                'form_answer_id',
                'scanned_by_name',
                'scanned_by_email',
            ]);

            $event->attendances()
                ->with(['formAnswer.user:id,name,email', 'scannedBy:id,name,email'])
                ->orderBy('scanned_at')
                ->chunk(500, function ($rows) use ($out): void {
                    foreach ($rows as $row) {
                        $user = $row->formAnswer?->user;
                        $scanner = $row->scannedBy;
                        fputcsv($out, [
                            $row->scanned_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                            $user?->name ?? '',
                            $user?->email ?? '',
                            $row->form_answer_id,
                            $scanner?->name ?? '',
                            $scanner?->email ?? '',
                        ]);
                    }
                });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

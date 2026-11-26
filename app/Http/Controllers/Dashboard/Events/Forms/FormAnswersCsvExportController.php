<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Registration\FormAnswersExportService;
use App\Support\Export\CsvStream;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Export CSV semua jawaban satu form, satu kolom per field, dengan signed URL file. */
class FormAnswersCsvExportController extends Controller
{
    public function __invoke(
        Event $event,
        Form $form,
        FormAnswersExportService $export,
    ): StreamedResponse {
        $this->authorize('view', $event);
        abort_unless($form->event_id === $event->id, 404);

        $fields = $export->fields($form);
        $header = $export->csvHeader($fields);
        $actorId = auth()->id();
        $fileName = 'jawaban-'.$form->id.'.csv';

        return response()->streamDownload(function () use ($event, $form, $export, $fields, $header, $actorId): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            $rowCount = 0;

            try {
                CsvStream::write(
                    $out,
                    $header,
                    $export->query($form)
                        ->lazy(200)
                        ->map(fn (FormAnswer $answer): array => $export->csvRow($answer, $fields)),
                    $rowCount,
                );
            } finally {
                fclose($out);

                Log::info('form_answers_export.generated', [
                    'actor_id' => $actorId,
                    'event_id' => (string) $event->getKey(),
                    'form_id' => (string) $form->getKey(),
                    'mode' => 'csv',
                    'rows' => $rowCount,
                ]);
            }
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

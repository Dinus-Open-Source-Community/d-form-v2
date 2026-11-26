<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Exceptions\FormAnswersArchiveTooLargeException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Services\Registration\FormAnswersArchiveBuilder;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Export ZIP semua jawaban satu form, terurut per pengirim, beserta seluruh lampiran. */
class FormAnswersZipExportController extends Controller
{
    public function __invoke(
        Event $event,
        Form $form,
        FormAnswersArchiveBuilder $builder,
    ): BinaryFileResponse {
        $this->authorize('view', $event);
        abort_unless($form->event_id === $event->id, 404);

        $actorId = auth()->id();

        try {
            $archive = $builder->build($form);
        } catch (FormAnswersArchiveTooLargeException $exception) {
            Log::warning('form_answers_export.too_large', [
                'actor_id' => $actorId,
                'event_id' => (string) $event->getKey(),
                'form_id' => (string) $form->getKey(),
                'files' => $exception->fileCount,
                'bytes' => $exception->byteCount,
            ]);

            abort(413, __('messages.form.export.too_large'));
        }

        Log::info('form_answers_export.generated', [
            'actor_id' => $actorId,
            'event_id' => (string) $event->getKey(),
            'form_id' => (string) $form->getKey(),
            'mode' => 'zip',
            'rows' => $archive['rows'],
            'files' => $archive['files'],
            'bytes' => $archive['bytes'],
        ]);

        return response()
            ->download($archive['path'], $archive['filename'], [
                'Content-Type' => 'application/zip',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }
}

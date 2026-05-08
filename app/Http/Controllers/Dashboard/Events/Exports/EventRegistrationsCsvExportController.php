<?php

namespace App\Http\Controllers\Dashboard\Events\Exports;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FormAnswer;
use App\Services\Registration\RegistrationAnswersSummarizer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventRegistrationsCsvExportController extends Controller
{
    public function __invoke(Event $event): StreamedResponse
    {
        $this->authorize('view', $event);

        $summarizer = app(RegistrationAnswersSummarizer::class);

        $fileName = 'registrations-event-'.$event->id.'.csv';

        return response()->streamDownload(function () use ($event, $summarizer): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'submission_id',
                'form_id',
                'form_title',
                'user_name',
                'user_email',
                'registration_code',
                'review_status',
                'submitted_at',
                'answers_summary',
            ]);

            FormAnswer::query()
                ->join('forms', 'form_answers.form_id', '=', 'forms.id')
                ->with(['user:id,name,email', 'form:id,title'])
                ->whereNull('forms.deleted_at')
                ->where('forms.event_id', $event->id)
                ->select('form_answers.*')
                ->orderByDesc('form_answers.created_at')
                ->chunk(250, function ($answers) use ($out, $summarizer): void {
                    foreach ($answers as $answer) {
                        /** @var FormAnswer $answer */
                        $form = $answer->form;
                        $user = $answer->user;

                        $summaryParts = [];
                        foreach ($summarizer->summarize($answer) as $label => $value) {
                            $summaryParts[] = $label.': '.$value;
                        }

                        fputcsv($out, [
                            $answer->id,
                            $answer->form_id,
                            $form?->title ?? '',
                            $user?->name ?? '',
                            $user?->email ?? '',
                            (string) ($answer->registration_code ?? ''),
                            $answer->review_status?->value ?? '',
                            $answer->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                            implode(' | ', $summaryParts),
                        ]);
                    }
                });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

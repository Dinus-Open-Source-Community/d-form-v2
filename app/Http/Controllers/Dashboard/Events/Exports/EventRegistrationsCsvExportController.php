<?php

namespace App\Http\Controllers\Dashboard\Events\Exports;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FormAnswer;
use App\Services\Registration\RegistrationAnswersSummarizer;
use App\Support\CsvCellSanitizer;
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
                'group_token',
                'registration_role',
                'member_confirmation_status',
                'leader_email',
                'submitted_at',
                'answers_summary',
            ]);

            FormAnswer::query()
                ->join('forms', 'form_answers.form_id', '=', 'forms.id')
                ->with(['user:id,name,email', 'form:id,title', 'teamLeaderSubmission.user:id,email'])
                ->whereNull('forms.deleted_at')
                ->where('forms.event_id', $event->id)
                ->select('form_answers.*')
                ->orderByDesc('form_answers.created_at')
                ->chunk(250, function ($answers) use ($out, $summarizer): void {
                    foreach ($answers as $answer) {
                        /** @var FormAnswer $answer */
                        $form = $answer->form;
                        $user = $answer->user;
                        $leaderEmail = $answer->teamLeaderSubmission?->user?->email ?? '';

                        $summaryParts = [];
                        foreach ($summarizer->summarize($answer) as $label => $value) {
                            $summaryParts[] = $label.': '.$value;
                        }

                        fputcsv($out, array_map(CsvCellSanitizer::sanitize(...), [
                            $answer->id,
                            $answer->form_id,
                            $form?->title ?? '',
                            $user?->name ?? '',
                            $user?->email ?? '',
                            (string) ($answer->registration_code ?? ''),
                            $answer->review_status?->value ?? '',
                            $answer->group_token ?? '',
                            $answer->registration_role?->value ?? '',
                            $answer->member_confirmation_status?->value ?? '',
                            $leaderEmail,
                            $answer->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                            implode(' | ', $summaryParts),
                        ]));
                    }
                });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

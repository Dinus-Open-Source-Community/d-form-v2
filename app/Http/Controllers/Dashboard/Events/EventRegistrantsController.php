<?php

namespace App\Http\Controllers\Dashboard\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Event\EventService;
use Inertia\Inertia;
use Inertia\Response;

class EventRegistrantsController extends Controller
{
    public function __invoke(Event $event, EventService $eventService): Response
    {
        $this->authorize('view', $event);

        $forms = Form::query()
            ->where('event_id', $event->id)
            ->published()
            ->orderBy('title')
            ->get(['id', 'title']);

        $formSummaries = $forms
            ->map(fn (Form $form): array => [
                'id' => $form->id,
                'title' => $form->title,
            ])
            ->values()
            ->all();

        $formIds = $forms->pluck('id')->all();

        $registrants = [];
        if ($formIds !== []) {
            $registrants = FormAnswer::query()
                ->with(['user:id,name,email', 'form:id,title'])
                ->whereIn('form_id', $formIds)
                ->whereListedForOrganizerParticipantRoster()
                ->orderByDesc('created_at')
                ->get()
                ->filter(fn (FormAnswer $row): bool => $row->user !== null)
                ->map(fn (FormAnswer $row): array => [
                    'id' => $row->id,
                    'form_id' => $row->form_id,
                    'form' => [
                        'id' => $row->form_id,
                        'title' => $row->form?->title ?? '',
                    ],
                    'user' => [
                        'id' => $row->user->id,
                        'name' => $row->user->name,
                        'email' => $row->user->email,
                        'avatar' => null,
                    ],
                    'event_id' => $event->id,
                    'status' => $row->review_status->value,
                    'submitted_at' => $row->created_at->toIso8601String(),
                    'answers' => self::stringifyAnswers(is_array($row->answers) ? $row->answers : []),
                    'registration_code' => $row->registration_code,
                    'reviewed_at' => $row->reviewed_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Dashboard/Events/Registrants', [
            'event' => $eventService->eventToInertiaArray($event),
            'forms' => $formSummaries,
            'registrants' => $registrants,
        ]);
    }

    /**
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, string>
     *
     */
    private static function stringifyAnswers(array $answers): array
    {
        $out = [];
        foreach ($answers as $key => $value) {
            if (is_array($value)) {
                $out[$key] = implode(', ', array_map(fn ($v) => (string) $v, $value));
            } elseif ($value === null) {
                $out[$key] = '';
            } else {
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }
}

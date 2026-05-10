<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Enums\FormAccessStatus;
use App\Models\Event;
use App\Models\Form;
use App\Services\Event\EventService;
use App\Services\Event\UserPortalEventResolver;
use App\Services\Form\FormAccessGuard;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserEventRegistrationFormPickerController
{
    public function __invoke(string $event_segment, EventService $eventService): Response|RedirectResponse
    {
        /** @var Event $event */
        $event = app(UserPortalEventResolver::class)->resolvePublished($event_segment);

        $forms = Form::query()
            ->where('event_id', $event->id)
            ->orderBy('title')
            ->get();

        if ($forms->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Belum ada formulir pendaftaran untuk acara ini.'),
            ]);

            return redirect()->route('dashboard.user.events.show', [
                'event_segment' => $event->slug ?? $event->getKey(),
            ]);
        }

        $user = auth()->user();
        \assert($user !== null);

        $formsPayload = $forms->map(function (Form $form) use ($event, $user): array {
            $status = FormAccessGuard::check($form, $event, $user);

            return [
                'id' => $form->id,
                'title' => $form->title,
                'description' => $form->description,
                'fill_url' => route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false),
                'access_status' => $status->value,
                'access_message' => $status->message(),
                'can_start' => $status === FormAccessStatus::Allowed,
            ];
        })->values()->all();

        return Inertia::render('Dashboard/User/EventRegistrationPickForm', [
            'event' => $eventService->eventToInertiaArray($event),
            'forms' => $formsPayload,
        ]);
    }
}

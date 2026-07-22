<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormSubmitSuccessController extends Controller
{
    public function __invoke(Request $request, Event $event, Form $form): Response
    {
        abort_unless($form->event_id === $event->id, 404);

        $user = $request->user();
        \assert($user !== null);

        $hasSubmission = FormAnswer::query()
            ->where('form_id', $form->id)
            ->where('user_id', $user->id)
            ->excludeRejectedSubmissions()
            ->exists();

        abort_unless($hasSubmission, 404);

        $successContent = is_string($form->success_content) ? trim($form->success_content) : '';
        if ($successContent === '' || $successContent === '<p></p>') {
            $successContent = null;
        }

        $isRegistration = $form->isRegistrationForm();

        return Inertia::render('Dashboard/Events/Forms/SubmitSuccess', [
            'event' => [
                'id' => $event->id,
                'slug' => $event->slug,
                'title' => $event->title,
            ],
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'purpose' => $form->purpose()->value,
                'success_content' => $successContent,
            ],
            'isRegistrationForm' => $isRegistration,
            'eventUrl' => route('dashboard.user.events.show', [
                'event_segment' => $event->slug ?? $event->getKey(),
            ], absolute: false),
            'registrationUrl' => $isRegistration
                ? route('dashboard.user.events.registration', [
                    'event_segment' => $event->slug ?? $event->getKey(),
                ], absolute: false)
                : null,
        ]);
    }
}

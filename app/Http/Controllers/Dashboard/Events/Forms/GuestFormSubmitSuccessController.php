<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Services\Form\GuestFormSubmissionOwnership;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Renders success only for the current eligible guest browser and form. */
final class GuestFormSubmitSuccessController extends Controller
{
    public function __invoke(Request $request, Event $event, Form $form): Response
    {
        GuestFormSubmissionOwnership::assertOwned($request, $event, $form);
        return Inertia::render('Dashboard/Events/Forms/SubmitSuccess', ['event' => ['id' => $event->id, 'slug' => $event->slug, 'title' => $event->title], 'form' => ['id' => $form->id, 'title' => $form->title, 'purpose' => $form->purpose()->value, 'success_content' => $form->success_content], 'isRegistrationForm' => false, 'eventUrl' => route('dashboard.user.events.show', ['event_segment' => $event->slug ?? $event->getKey()], false), 'registrationUrl' => null]);
    }
}

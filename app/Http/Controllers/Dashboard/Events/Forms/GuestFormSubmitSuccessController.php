<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Services\Form\FormSubmissionLimit;
use App\Services\Form\GuestFormAccess;
use App\Services\Form\GuestFormSubmissionOwnership;
use App\Services\Form\GuestFormToken;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Renders success only for the current guest browser that owns a submission. */
final class GuestFormSubmitSuccessController extends Controller
{
    public function __invoke(Request $request, Event $event, Form $form): Response
    {
        GuestFormSubmissionOwnership::assertOwned($request, $event, $form);
        $token = $request->cookie(GuestFormToken::COOKIE_NAME);
        if (! is_string($token)) {
            abort(404);
        }

        return Inertia::render('Dashboard/Events/Forms/SubmitSuccess', ['event' => ['id' => $event->id, 'slug' => $event->slug, 'title' => $event->title], 'form' => ['id' => $form->id, 'title' => $form->title, 'purpose' => $form->purpose()->value, 'success_content' => $form->success_content], 'isRegistrationForm' => false, 'eventUrl' => route('dashboard.user.events.show', ['event_segment' => $event->slug ?? $event->getKey()], false), 'registrationUrl' => null, 'fillUrl' => route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false), 'canSubmitAgain' => GuestFormAccess::isEligible($event, $form)
                && FormSubmissionLimit::canSubmitAgain($form, GuestFormToken::hash($token))]);
    }
}

<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Services\Form\GuestFormAccess;
use Illuminate\Http\RedirectResponse;

final class FormShareRedirectController extends Controller
{
    public function __invoke(string $slug): RedirectResponse
    {
        $form = Form::query()
            ->published()
            ->where('share_slug', $slug)
            ->firstOrFail();
        $event = Event::query()->findOrFail($form->event_id);
        $participantPath = route('dashboard.events.forms.fill', [
            'event' => $event,
            'form' => $form,
        ], false);

        if (auth()->guest()) {
            $target = GuestFormAccess::isEligible($event, $form)
                ? route('dashboard.public.events.forms.fill', ['event' => $event, 'form' => $form], false)
                : $participantPath;

            return redirect()->to($target === $participantPath ? route('auth.login', ['intended' => $participantPath], false) : $target);
        }

        return redirect()->to($participantPath);
    }
}

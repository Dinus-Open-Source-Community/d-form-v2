<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormSubmissionsController extends Controller
{
    /** Halaman pengiriman mandiri sudah tidak dipakai: daftar jawaban kini dirender langsung di tab "Jawaban" halaman detail form. */
    public function __invoke(Request $request, Event $event, Form $form): RedirectResponse
    {
        $this->authorize('view', $event);

        abort_unless($form->event_id === $event->id, 404);

        return redirect()
            ->route('dashboard.events.forms.show', [
                'event' => $event,
                'form' => $form,
                'tab' => 'jawaban',
            ])
            ->setStatusCode(301);
    }
}

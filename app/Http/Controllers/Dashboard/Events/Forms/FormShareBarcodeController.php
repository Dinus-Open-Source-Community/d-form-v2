<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\FormStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Services\Form\FormShareBarcodeGenerator;
use Illuminate\Http\Response;

final class FormShareBarcodeController extends Controller
{
    public function __invoke(
        Event $event,
        Form $form,
        FormShareBarcodeGenerator $barcodeGenerator,
    ): Response {
        $this->guardFormOnEvent($event, $form);
        abort_unless($form->status === FormStatus::Published, 404);

        $participantPath = route('dashboard.events.forms.fill', [
            'event' => $event,
            'form' => $form,
        ]);

        return response($barcodeGenerator->pngForUrl($participantPath), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="formulir-barcode.png"',
        ]);
    }

    private function guardFormOnEvent(Event $event, Form $form): void
    {
        abort_unless($form->event_id === $event->id, 404);
        abort_unless($form->isDraftVisibleTo($event, auth()->user()), 404);
        $this->authorize('update', $event);
    }
}

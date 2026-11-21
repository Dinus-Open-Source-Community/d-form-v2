<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\FormPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormSessionRequest;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FormSessionController extends Controller
{
    public function store(StoreFormSessionRequest $request, Event $event, Form $form): RedirectResponse
    {
        $this->guardForm($event, $form);

        $data = $request->validated();

        DB::transaction(function () use ($event, $form, $data): void {
            $lockedForm = Form::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();

            if ($lockedForm->formSessions()->count() >= 100) {
                throw ValidationException::withMessages(['session_date' => 'Maksimal 100 sesi dapat dibuat.']);
            }

            if ($lockedForm->formSessions()->whereDate('session_date', $data['session_date'])->exists()) {
                throw ValidationException::withMessages(['session_date' => 'Tanggal sesi sudah digunakan.']);
            }

            $lockedForm->formSessions()->create([
                'session_date' => $data['session_date'],
                'label' => $data['label'] ?? null,
            ]);
        });

        return redirect()->route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]);
    }

    public function destroy(Event $event, Form $form, FormSession $session): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->guardForm($event, $form);
        abort_unless($session->form_id === $form->id, 404);

        if (EventAttendance::query()->where('form_session_id', $session->id)->exists()) {
            throw ValidationException::withMessages(['session' => 'Sesi dengan kehadiran tidak dapat dihapus.']);
        }

        $session->delete();

        return redirect()->route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]);
    }

    private function guardForm(Event $event, Form $form): void
    {
        $this->authorize('update', $event);
        abort_unless($form->event_id === $event->id, 404);
        abort_unless($form->purpose() === FormPurpose::DKelas, 403);
    }
}

<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Services\Event\EventService;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\HomeController as DashboardHomeController;

Route::middleware('auth')->get('/admin', fn () => to_route('dashboard.home'));

Route::middleware('auth')->get('/dashboard', DashboardHomeController::class)->name('dashboard.home');

Route::middleware('auth')->get('/dashboard/profile', fn () => inertia('Dashboard/Profile'))->name('dashboard.profile');

Route::middleware('auth')->prefix('/dashboard/user')->name('dashboard.user.')->group(function () {
    Route::get('/events', function (EventService $eventService) {
        $events = Event::query()
            ->where('status', EventStatus::Published)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Event $e) => $eventService->eventToInertiaArray($e))
            ->values()
            ->all();

        return inertia('Dashboard/User/Events', [
            'events' => $events,
        ]);
    })->name('events');

    Route::get('/events/{event}', function (Event $event, EventService $eventService) {
        $user = auth()->user();
        $registration = \App\Models\FormAnswer::where('user_id', $user->id)
            ->whereHas('form', function ($q) use ($event) {
                $q->where('event_id', $event->id);
            })
            ->first();

        return inertia('Dashboard/User/EventDetail', [
            'event' => $eventService->eventToInertiaArray($event),
            'isRegistered' => !!$registration,
            'registrationStatus' => $registration?->status,
        ]);
    })->name('events.show');

    Route::get('/events/{event}/register', function (Event $event, EventService $eventService) {
        $form = Form::where('event_id', $event->id)->orderBy('title')->first();
        $fields = $form ? $form->formFields()->orderBy('order')->get()->map(function ($f) {
            return [
                'id' => $f->id,
                'type' => \App\Support\FormFieldTypeMapping::toApiType($f->input_type),
                'label' => $f->label,
                'description' => $f->description,
                'name' => $f->name,
                'order' => $f->order,
                'metadata' => $f->metadata,
            ];
        }) : [];

        return inertia('Dashboard/User/EventRegister', [
            'event' => $eventService->eventToInertiaArray($event),
            'form' => $form,
            'fields' => $fields,
            'submitUrl' => route('dashboard.forms.submission', ['event' => $event, 'form' => $form]),
        ]);
    })->name('events.register');
});

Route::middleware('auth')->prefix('/dashboard/events/{event}')->name('dashboard.events.')->group(function () {
    Route::get('/scan', fn () => inertia('Dashboard/Events/Scan'))->name('scan');
    Route::get('/registrants', fn () => inertia('Dashboard/Events/Registrants'))->name('registrants');
});

<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Services\Event\EventService;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\HomeController as DashboardHomeController;
use Inertia\Inertia;

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
            'isRegistered' => (bool) $registration,
            'registrationStatus' => $registration ? 'submitted' : null,
        ]);
    })->name('events.show');

    Route::get('/events/{event}/register', function (Event $event) {
        $form = Form::query()->where('event_id', $event->id)->orderBy('title')->first();

        if ($form === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No registration form has been published for this event yet.',
            ]);

            return redirect()->route('dashboard.user.events.show', $event);
        }

        return redirect()->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form]);
    })->name('events.register');
});

Route::middleware('auth')->prefix('/dashboard/events/{event}')->name('dashboard.events.')->group(function () {
    Route::get('/scan', fn () => inertia('Dashboard/Events/Scan'))->name('scan');
    Route::get('/registrants', fn () => inertia('Dashboard/Events/Registrants'))->name('registrants');
});

<?php

use App\Enums\EventStatus;
use App\Http\Controllers\Dashboard\Events\EventRegistrantsController;
use App\Http\Controllers\Dashboard\User\UserEventRegistrationController;
use App\Models\Event;
use App\Models\Form;
use App\Services\Event\EventService;
use App\Services\Event\UserPortalEventResolver;
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

    Route::get('/events/{event_segment}/registration', UserEventRegistrationController::class)
        ->name('events.registration');

    Route::get('/events/{event_segment}/register', function (string $event_segment) {
        $event = app(UserPortalEventResolver::class)->resolvePublished($event_segment);

        $form = Form::query()->where('event_id', $event->id)->orderBy('title')->first();

        if ($form === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No registration form has been published for this event yet.',
            ]);

            return redirect()->route('dashboard.user.events.show', ['event_segment' => $event->slug ?? $event->getKey()]);
        }

        return redirect()->route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form]);
    })->name('events.register');

    Route::get('/events/{event_segment}', function (string $event_segment, EventService $eventService) {
        $event = app(UserPortalEventResolver::class)->resolvePublished($event_segment);

        $user = auth()->user();
        $registration = \App\Models\FormAnswer::where('user_id', $user->id)
            ->whereHas('form', function ($q) use ($event) {
                $q->where('event_id', $event->id);
            })
            ->first();

        return inertia('Dashboard/User/EventDetail', [
            'event' => $eventService->eventToInertiaArray($event),
            'isRegistered' => (bool) $registration,
            'registrationStatus' => $registration?->review_status?->value,
        ]);
    })->name('events.show');
});

Route::middleware('auth')->prefix('/dashboard/events/{event}')->name('dashboard.events.')->group(function () {
    Route::get('/scan', fn () => inertia('Dashboard/Events/Scan'))->name('scan');
    Route::get('/registrants', EventRegistrantsController::class)->name('registrants');
});

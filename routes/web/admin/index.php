<?php

use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Http\Controllers\Dashboard\Events\AttendanceScanController;
use App\Http\Controllers\Dashboard\Events\EventRegistrantsController;
use App\Http\Controllers\Dashboard\User\TeamInvitationController;
use App\Http\Controllers\Dashboard\User\UserEventRegistrationController;
use App\Models\Event;
use App\Models\Form;
use App\Services\Event\EventService;
use App\Services\Event\UserPortalEventResolver;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\EventReportingController;
use App\Http\Controllers\Dashboard\Events\Exports\EventAttendanceCsvExportController;
use App\Http\Controllers\Dashboard\Events\Exports\EventRegistrationsCsvExportController;
use App\Http\Controllers\Dashboard\HomeController as DashboardHomeController;
use App\Http\Controllers\Dashboard\ProfileController;
use Inertia\Inertia;

Route::middleware('auth')->get('/admin', fn () => to_route('dashboard.home'));

Route::middleware('auth')->get('/dashboard', DashboardHomeController::class)->name('dashboard.home');

Route::middleware(['auth', 'organizer'])->get('/dashboard/reports', EventReportingController::class)->name('dashboard.reports.index');

Route::middleware('auth')->get('/dashboard/profile', fn () => inertia('Dashboard/Profile'))->name('dashboard.profile');
Route::middleware(['auth', 'throttle:10,1'])->patch('/dashboard/profile', [ProfileController::class, 'update'])->name('dashboard.profile.update');
Route::middleware(['auth', 'throttle:10,1'])->put('/dashboard/profile/password', [ProfileController::class, 'updatePassword'])->name('dashboard.profile.password.update');

Route::middleware(['auth', 'member_portal'])->prefix('/dashboard/user')->name('dashboard.user.')->group(function () {
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

    Route::get('/team-invitations/{token}', [TeamInvitationController::class, 'show'])
        ->name('team-invitations.show');
    Route::post('/team-invitations/{token}', [TeamInvitationController::class, 'update'])
        ->name('team-invitations.update');

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

    Route::get('/events/{event_segment}', function (
        string $event_segment,
        EventService $eventService,
        RegistrationQrPngGenerator $qrGenerator,
    ) {
        $event = app(UserPortalEventResolver::class)->resolvePublished($event_segment);

        $user = auth()->user();
        $registration = \App\Models\FormAnswer::where('user_id', $user->id)
            ->whereHas('form', function ($q) use ($event) {
                $q->where('event_id', $event->id);
            })
            ->first();

        $qrBase64 = null;
        $registrationCode = null;
        if ($registration && $registration->review_status === FormAnswerReviewStatus::Accepted) {
            $registrationCode = $registration->registration_code;
            $png = $qrGenerator->pngForSubmission($registration->id);
            $qrBase64 = base64_encode($png);
        }

        return inertia('Dashboard/User/EventDetail', [
            'event' => $eventService->eventToInertiaArray($event),
            'isRegistered' => (bool) $registration,
            'registrationStatus' => $registration?->review_status?->value,
            'qr_base64' => $qrBase64,
            'registration_code' => $registrationCode,
        ]);
    })->name('events.show');
});

Route::middleware(['auth', 'organizer'])->prefix('/dashboard/events/{event}')->name('dashboard.events.')->group(function () {
    Route::get('/exports/registrations.csv', EventRegistrationsCsvExportController::class)->name('exports.registrations-csv');
    Route::get('/exports/attendance.csv', EventAttendanceCsvExportController::class)->name('exports.attendance-csv');
    Route::get('/scan', [AttendanceScanController::class, 'show'])->name('scan');
    Route::post('/attendance-scan', [AttendanceScanController::class, 'store'])->name('attendance-scan.store');
    Route::get('/registrants', EventRegistrantsController::class)->name('registrants');
});

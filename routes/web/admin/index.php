<?php

use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Http\Controllers\Dashboard\Events\AttendanceScanController;
use App\Http\Controllers\Dashboard\Events\EventRegistrantsController;
use App\Http\Controllers\Dashboard\User\TeamInvitationController;
use App\Http\Controllers\Dashboard\User\UserEventRegistrationController;
use App\Http\Controllers\Dashboard\User\UserEventRegistrationFormPickerController;
use App\Models\Event;
use App\Services\Event\EventService;
use App\Services\Event\UserPortalEventResolver;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\EventReportingController;
use App\Http\Controllers\Dashboard\Events\Exports\EventAttendanceCsvExportController;
use App\Http\Controllers\Dashboard\Events\Exports\EventRegistrationsCsvExportController;
use App\Http\Controllers\Dashboard\HomeController as DashboardHomeController;
use App\Http\Controllers\Dashboard\ProfileController;

Route::middleware('auth')->get('/admin', fn () => to_route('dashboard.home'));

/** Bekas /dashboard — arahkan ke area yang sesuai peran. */
Route::middleware('auth')->get('/dashboard', function () {
    return auth()->user()->can('events.list')
        ? redirect()->route('dashboard.home')
        : redirect()->route('dashboard.user.events');
});

Route::permanentRedirect('/dashboard/user/events', '/user/dashboard');

Route::middleware('auth')->get('/dashboard/profile', fn () => inertia('Dashboard/Profile'))->name('dashboard.profile');
Route::middleware(['auth', 'throttle:10,1'])->patch('/dashboard/profile', [ProfileController::class, 'update'])->name('dashboard.profile.update');
Route::middleware(['auth', 'throttle:10,1'])->put('/dashboard/profile/password', [ProfileController::class, 'updatePassword'])->name('dashboard.profile.password.update');
Route::middleware(['auth', 'organizer'])->prefix('/admin/dashboard')->group(function () {
    Route::get('/', DashboardHomeController::class)->name('dashboard.home');
    Route::get('/reports', EventReportingController::class)->name('dashboard.reports.index');
    Route::get('/recruitment', fn () => inertia('Dashboard/Recruitment/Index'))->name('dashboard.recruitment.index');
});

Route::middleware('auth')->get('/user/dashboard/profile', fn () => inertia('Dashboard/Profile'))->name('dashboard.profile');

Route::middleware(['auth', 'member_portal'])->prefix('/user/dashboard')->name('dashboard.user.')->group(function () {
    Route::get('/', function (EventService $eventService) {
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

    Route::get('/events/{event_segment}/register', UserEventRegistrationFormPickerController::class)
        ->name('events.register');

    Route::get('/events/{event_segment}', function (
        string $event_segment,
        EventService $eventService,
        RegistrationQrPngGenerator $qrGenerator,
    ) {
        $event = app(UserPortalEventResolver::class)->resolvePublished($event_segment);

        $user = auth()->user();
        $registration = \App\Models\FormAnswer::query()
            ->where('user_id', $user->id)
            ->whereHas('form', function ($q) use ($event) {
                $q->where('event_id', $event->id);
            })
            ->excludeTerminatedInvitationMembers()
            ->orderByDesc('created_at')
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

Route::middleware(['auth', 'organizer'])->prefix('/admin/dashboard/events/{event}')->name('dashboard.events.')->group(function () {
    Route::get('/exports/registrations.csv', EventRegistrationsCsvExportController::class)->name('exports.registrations-csv');
    Route::get('/exports/attendance.csv', EventAttendanceCsvExportController::class)->name('exports.attendance-csv');
    Route::get('/scan', [AttendanceScanController::class, 'show'])->name('scan');
    Route::post('/attendance-scan', [AttendanceScanController::class, 'store'])->name('attendance-scan.store');
    Route::get('/registrants', EventRegistrantsController::class)->name('registrants');
});

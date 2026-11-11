<?php

use App\Http\Controllers\Dashboard\Scan\GlobalScanController;
use App\Http\Controllers\Dashboard\Scan\GlobalScanExportController;
use App\Http\Controllers\Dashboard\Scan\GlobalScanFeedController;
use App\Http\Controllers\Dashboard\Events\EventRegistrantsController;
use App\Http\Controllers\Dashboard\User\TeamInvitationController;
use App\Http\Controllers\Dashboard\User\UserBrowseEventsController;
use App\Http\Controllers\Dashboard\User\UserEventPortalController;
use App\Http\Controllers\Dashboard\User\UserEventRegistrationController;
use App\Http\Controllers\Dashboard\User\UserEventRegistrationFormPickerController;
use App\Http\Controllers\Dashboard\User\UserJoinedEventsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\Events\Exports\EventAttendanceCsvExportController;
use App\Http\Controllers\Dashboard\Events\Exports\EventRegistrationsCsvExportController;
use App\Http\Controllers\Dashboard\HomeController as DashboardHomeController;
use App\Http\Controllers\Dashboard\ProfileController;
use App\Http\Controllers\Dashboard\User\MemberDashboardController;

Route::middleware('auth')->get('/admin', fn () => to_route('dashboard'))->name('admin.index');

/** Dasbor utama — admin → organizer overview, member → member overview. */
Route::middleware('auth')->get('/dashboard', function () {
    if (auth()->user()->can('events.list')) {
        return app(DashboardHomeController::class)(request());
    }

    return app(MemberDashboardController::class)(request());
})->name('dashboard');

// Redirect lawas skema /user/dashboard/* (dari backup-oprec-removal) — target
// route-name terverifikasi hidup di skema /joined. Redirect statis skema lama
// sengaja tidak dibawa (target path-nya sudah tidak ada di main).
Route::get(
    '/user/dashboard/events/{event_segment}/register',
    fn (string $event_segment) => redirect()->route('dashboard.user.events.register', ['event_segment' => $event_segment], 301)
);
Route::get(
    '/user/dashboard/events/{event_segment}/registration',
    fn (string $event_segment) => redirect()->route('dashboard.user.events.registration', ['event_segment' => $event_segment], 301)
);
Route::get(
    '/user/dashboard/team-invitations/{token}',
    fn (string $token) => redirect()->route('dashboard.user.team-invitations.show', ['token' => $token], 301)
);
Route::get(
    '/user/dashboard/events/{event_segment}',
    fn (string $event_segment) => redirect()->route('dashboard.user.events.show', ['event_segment' => $event_segment], 301)
);

Route::middleware('auth')->prefix('/admin/scan')->name('dashboard.scan.')->group(function () {
    Route::get('/', [GlobalScanController::class, 'show'])->middleware('throttle:scan-page')->name('index');
    Route::get('/feed', [GlobalScanFeedController::class, 'feed'])->middleware('throttle:scan-feed')->name('feed');
    Route::get('/export', [GlobalScanExportController::class, 'export'])->middleware('throttle:scan-export')->name('export');
    Route::post('/', [GlobalScanController::class, 'store'])->middleware('throttle:scan-store')->name('store');
});

Route::middleware('auth')->get('/profile', fn () => inertia('Dashboard/Profile'))->name('dashboard.profile');
Route::middleware(['auth', 'throttle:10,1'])->patch('/profile', [ProfileController::class, 'update'])->name('dashboard.profile.update');
Route::middleware(['auth', 'throttle:10,1'])->post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('dashboard.profile.avatar.update');
Route::middleware(['auth', 'throttle:10,1'])->delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('dashboard.profile.avatar.destroy');
Route::middleware(['auth', 'throttle:10,1'])->put('/profile/password', [ProfileController::class, 'updatePassword'])->name('dashboard.profile.password.update');
Route::middleware('auth')->get('/admin/dashboard', fn () => redirect()->route('dashboard'))->name('dashboard.home');
Route::middleware(['auth', 'organizer'])->prefix('/admin')->group(function () {
    Route::get('/reports', fn () => redirect()->route('dashboard.events.index'))->name('dashboard.reports.index');
});

Route::middleware('auth')->get('/joined/profile', fn () => redirect()->route('dashboard.profile'))->name('dashboard.user.profile');

Route::middleware(['auth', 'member_portal'])->prefix('/joined')->name('dashboard.user.')->group(function () {
    Route::get('/', UserJoinedEventsController::class)->name('events');

    Route::get('/{event_segment}/registration', UserEventRegistrationController::class)
        ->name('events.registration');

    Route::get('/team-invitations/{token}', [TeamInvitationController::class, 'show'])
        ->name('team-invitations.show');
    Route::post('/team-invitations/{token}', [TeamInvitationController::class, 'update'])
        ->name('team-invitations.update');

    Route::get('/{event_segment}/register', UserEventRegistrationFormPickerController::class)
        ->name('events.register');

    Route::get('/{event_segment}', UserEventPortalController::class)->name('events.show');
});

Route::middleware(['auth', 'member_portal'])->get('/browse', UserBrowseEventsController::class)
    ->name('dashboard.user.events.browse');

Route::middleware(['auth', 'organizer'])->prefix('/admin/events/{event}')->name('dashboard.events.')->group(function () {
    Route::get('/exports/registrations.csv', EventRegistrationsCsvExportController::class)
        ->middleware('throttle:recruitment-export')
        ->name('exports.registrations-csv');
    Route::get('/exports/attendance.csv', EventAttendanceCsvExportController::class)
        ->middleware('throttle:recruitment-export')
        ->name('exports.attendance-csv');
    Route::get('/scan', fn () => to_route('dashboard.scan.index'))->name('scan');
    Route::get('/registrants', EventRegistrantsController::class)->name('registrants');
});

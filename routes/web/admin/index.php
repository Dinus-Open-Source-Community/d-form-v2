<?php

use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Http\Controllers\Dashboard\Events\AttendanceScanController;
use App\Http\Controllers\Dashboard\Events\EventRegistrantsController;
use App\Http\Controllers\Dashboard\User\TeamInvitationController;
use App\Http\Controllers\Dashboard\User\UserEventRegistrationController;
use App\Http\Controllers\Dashboard\User\UserEventRegistrationFormPickerController;
use App\Models\Event;
use App\Models\FormAnswer;
use App\Services\Event\EventService;
use App\Services\Event\UserPortalEventResolver;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\Events\Exports\EventAttendanceCsvExportController;
use App\Http\Controllers\Dashboard\Events\Exports\EventRegistrationsCsvExportController;
use App\Http\Controllers\Dashboard\HomeController as DashboardHomeController;
use App\Http\Controllers\Dashboard\ProfileController;

Route::middleware('auth')->get('/admin', fn () => to_route('dashboard.home'));

/** Dasbor utama — admin ke area organizer, member ke overview peserta. */
Route::middleware('auth')->get('/dashboard', function () {
    return auth()->user()->can('events.list')
        ? redirect()->route('dashboard.home')
        : inertia('Dashboard/User/Index');
})->name('dashboard.user.overview');

Route::permanentRedirect('/dashboard/user/events', '/events/joined');
Route::permanentRedirect('/user/dashboard', '/events/joined');
Route::permanentRedirect('/user/dashboard/overview', '/dashboard');
Route::permanentRedirect('/user/dashboard/profile', '/dashboard/profile');
Route::permanentRedirect('/user/dashboard/events/browse', '/events/joined/events/browse');
Route::permanentRedirect('/user/dashboard/users/check-email', '/events/joined/users/check-email');
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

Route::middleware('auth')->get('/dashboard/profile', fn () => inertia('Dashboard/Profile'))->name('dashboard.profile');
Route::middleware(['auth', 'throttle:10,1'])->patch('/dashboard/profile', [ProfileController::class, 'update'])->name('dashboard.profile.update');
Route::middleware(['auth', 'throttle:10,1'])->post('/dashboard/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('dashboard.profile.avatar.update');
Route::middleware(['auth', 'throttle:10,1'])->delete('/dashboard/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('dashboard.profile.avatar.destroy');
Route::middleware(['auth', 'throttle:10,1'])->put('/dashboard/profile/password', [ProfileController::class, 'updatePassword'])->name('dashboard.profile.password.update');
Route::middleware(['auth', 'organizer'])->prefix('/admin/dashboard')->group(function () {
    Route::get('/', DashboardHomeController::class)->name('dashboard.home');
    Route::get('/reports', fn () => redirect()->route('dashboard.events.index'))->name('dashboard.reports.index');
    Route::get('/recruitment', fn () => inertia('Dashboard/Recruitment/Index'))->name('dashboard.recruitment.index');
});

Route::middleware('auth')->get('/events/joined/profile', fn () => redirect()->route('dashboard.profile'))->name('dashboard.user.profile');

Route::middleware(['auth', 'member_portal'])->prefix('/events/joined')->name('dashboard.user.')->group(function () {
    Route::get('/', function (EventService $eventService) {
        $userId = auth()->id();
        $events = Event::query()
            ->where('status', EventStatus::Published)
            ->whereHas('forms', function ($q) use ($userId): void {
                $q->whereHas('formAnswers', function ($aq) use ($userId): void {
                    $aq->where('user_id', $userId)
                        ->excludeTerminatedInvitationMembers();
                });
            })
            ->orderByDesc('start_date')
            ->get();

        $pendingInviteRows = FormAnswer::query()
            ->join('forms', 'forms.id', '=', 'form_answers.form_id')
            ->where('form_answers.user_id', $userId)
            ->where('form_answers.registration_role', RegistrationRole::Member->value)
            ->where('form_answers.member_confirmation_status', MemberConfirmationStatus::Pending->value)
            ->whereNotNull('form_answers.invitation_token')
            ->select(['form_answers.invitation_token', 'forms.event_id'])
            ->get();

        $inviteUrlByEventId = [];
        foreach ($pendingInviteRows as $row) {
            $inviteUrlByEventId[$row->event_id] = route('dashboard.user.team-invitations.show', ['token' => $row->invitation_token], false);
        }

        $payload = $events
            ->map(function (Event $e) use ($eventService, $inviteUrlByEventId): array {
                $data = $eventService->eventToInertiaArray($e);
                if (isset($inviteUrlByEventId[$e->id])) {
                    $data['pending_team_invitation_url'] = $inviteUrlByEventId[$e->id];
                }

                return $data;
            })
            ->values()
            ->all();

        return inertia('Dashboard/User/Events', [
            'events' => $payload,
            'listMode' => 'mine',
        ]);
    })->name('events');

    Route::get('/events/browse', function (EventService $eventService) {
        $events = Event::query()
            ->where('status', EventStatus::Published)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Event $e) => $eventService->eventToInertiaArray($e))
            ->values()
            ->all();

        return inertia('Dashboard/User/Events', [
            'events' => $events,
            'listMode' => 'browse',
        ]);
    })->name('events.browse');

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
        $registration = FormAnswer::query()
            ->where('user_id', $user->id)
            ->whereHas('form', function ($q) use ($event) {
                $q->where('event_id', $event->id);
            })
            ->excludeTerminatedInvitationMembers()
            ->orderByDesc('created_at')
            ->first();

        $hasPendingTeamInvitation = $registration?->isMemberPendingInvitation() ?? false;
        $pendingTeamInvitationUrl = null;
        if ($hasPendingTeamInvitation) {
            $token = $registration->invitation_token;
            if (is_string($token) && $token !== '') {
                $pendingTeamInvitationUrl = route('dashboard.user.team-invitations.show', ['token' => $token], false);
            }
        }

        $isPortalRegistered = (bool) $registration && ! $hasPendingTeamInvitation;

        $qrBase64 = null;
        $registrationCode = null;
        if ($isPortalRegistered && $registration->review_status === FormAnswerReviewStatus::Accepted) {
            $registrationCode = $registration->registration_code;
            $png = $qrGenerator->pngForSubmission($registration->id);
            $qrBase64 = base64_encode($png);
        }

        return inertia('Dashboard/User/EventDetail', [
            'event' => $eventService->eventToInertiaArray($event),
            'isRegistered' => $isPortalRegistered,
            'pendingTeamInvitationUrl' => $pendingTeamInvitationUrl,
            'registrationStatus' => $isPortalRegistered ? $registration?->review_status?->value : null,
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

<?php

namespace App\Http\Controllers\Dashboard\Scan;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalScanStoreRequest;
use App\Jobs\RecordAttendanceJob;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Services\Event\EventService;
use App\Services\Recruitment\AttendanceCheckInResolver;
use App\Services\Recruitment\AttendanceService;
use App\Services\Recruitment\InterviewSessionService;
use App\Services\Registration\BundleGuestDisplayNameResolver;
use App\Services\Registration\FormAnswerRecipientResolver;
use App\Services\Scan\GlobalScanResolver;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class GlobalScanController extends Controller
{
    public function __construct(
        private readonly GlobalScanResolver $scanResolver,
        private readonly AttendanceCheckInResolver $checkInResolver,
        private readonly AttendanceService $attendanceService,
        private readonly BundleGuestDisplayNameResolver $displayNameResolver,
        private readonly FormAnswerRecipientResolver $recipientResolver,
        private readonly InterviewSessionService $sessionService,
        private readonly EventService $eventService,
    ) {
    }

    public function show(): Response
    {
        $user = auth()->user();
        abort_unless(
            $user !== null && ($user->can('events.list') || $user->can('recruitment.attendance.scan')),
            403
        );

        $sessions = [];
        if ($user->can('recruitment.attendance.scan')) {
            $sessions = \App\Models\Recruitment\RecruitmentInterviewSession::query()
                ->where('is_active', true)
                ->whereDate('session_date', '>=', today())
                ->with(['division:id,name', 'period:id,name'])
                ->orderBy('session_date')
                ->orderBy('starts_at')
                ->get()
                ->map(fn ($session): array => $this->sessionService->toListArray($session))
                ->values()
                ->all();
        }

        $events = [];
        if ($user->can('events.list')) {
            $events = Event::query()
                ->where('status', EventStatus::Published)
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (Event $event): array => $this->eventService->eventToInertiaArray($event))
                ->values()
                ->all();
        }

        return Inertia::render('Dashboard/Scan/Global', [
            'targets' => ['sessions' => $sessions, 'events' => $events],
            'globalScanStoreUrl' => route('dashboard.scan.store'),
            'globalScanFeedUrl' => route('dashboard.scan.feed'),
        ]);
    }

    public function store(GlobalScanStoreRequest $request): JsonResponse
    {
        $raw = (string) $request->validated('raw');
        $desk = (string) ($request->validated('desk') ?? '');
        $resolved = $this->scanResolver->resolve($raw);

        if ($resolved['kind'] === 'recruitment') {
            $session = $this->checkInResolver->resolveSessionForApplication($resolved['application']);
            $this->authorize('scanAttendance', $session);

            $result = $this->attendanceService->checkInFromInput(
                $session,
                $resolved['registrationNumber'],
                $resolved['application']->id,
                $raw,
                $request->user(),
                $request,
            );
            $attendee = $this->attendanceService->applicantPayload($result['application']);
            $status = $result['duplicate'] ? 409 : 200;

            return response()->json([
                'type' => 'recruitment',
                'eventTitle' => 'Oprec · '.($session->division?->name ?? '').' · '.($session->session_date ?? ''),
                'attendee' => $attendee,
                'status' => $result['duplicate'] ? 'duplicate' : 'success',
                'scannedAt' => now()->toIso8601String(),
                'desk' => $desk,
            ], $status);
        }

        $answer = $resolved['answer'];
        $event = $answer->form->event;
        abort_unless($request->user() !== null && $request->user()->can('update', $event), 403);

        $already = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('form_answer_id', $answer->id)
            ->exists();

        $payload = [
            'type' => 'event',
            'eventTitle' => $event->title,
            'attendee' => [
                'name' => $this->displayNameResolver->resolve($answer),
                'email' => $this->recipientResolver->email($answer) ?? '',
                'form_answer_id' => $answer->id,
            ],
            'scannedAt' => now()->toIso8601String(),
            'desk' => $desk,
        ];

        if ($already) {
            $payload['status'] = 'duplicate';

            return response()->json($payload, 409);
        }

        RecordAttendanceJob::dispatch($event->id, $answer->id, $request->user()->id)->afterCommit();
        $payload['status'] = 'success';

        return response()->json($payload, 202);
    }
}

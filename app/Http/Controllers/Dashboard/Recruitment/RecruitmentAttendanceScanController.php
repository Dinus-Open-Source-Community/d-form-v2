<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\RecruitmentAttendanceScanStoreRequest;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Services\Recruitment\AttendanceService;
use App\Services\Recruitment\InterviewSessionService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentAttendanceScanController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly InterviewSessionService $sessionService,
    ) {
    }

    public function show(): Response
    {
        abort_unless(auth()->user()?->can('recruitment.attendance.scan'), 403);

        $sessions = RecruitmentInterviewSession::query()
            ->where('is_active', true)
            ->whereDate('session_date', '>=', today())
            ->with(['division:id,name', 'period:id,name'])
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (RecruitmentInterviewSession $session): array => $this->sessionService->toListArray($session))
            ->values()
            ->all();

        $initialSessionId = request()->query('session');
        if (! is_string($initialSessionId) || ! collect($sessions)->contains(fn (array $s): bool => $s['id'] === $initialSessionId)) {
            $initialSessionId = $sessions[0]['id'] ?? null;
        }

        return Inertia::render('Dashboard/Recruitment/AttendanceScan', [
            'sessions' => $sessions,
            'initialSessionId' => $initialSessionId,
            'attendanceScanStoreUrl' => route('dashboard.recruitment.attendance-scan.store'),
        ]);
    }

    public function store(RecruitmentAttendanceScanStoreRequest $request): JsonResponse
    {
        $session = $request->session();
        $this->authorize('scanAttendance', $session);

        $validated = $request->validated();

        $result = $this->attendanceService->checkInFromInput(
            $session,
            $validated['registration_number'] ?? null,
            $validated['application_id'] ?? null,
            $validated['raw_payload'] ?? null,
            $request->user(),
            $request,
        );

        $attendee = $this->attendanceService->applicantPayload($result['application']);

        if ($result['duplicate']) {
            return response()->json([
                'message' => __('This applicant has already checked in for this session.'),
                'attendee' => $attendee,
            ], 409);
        }

        return response()->json([
            'message' => __('Check-in recorded. Queue number :number.', [
                'number' => str_pad((string) ($result['queue']?->queue_number ?? 0), 2, '0', STR_PAD_LEFT),
            ]),
            'attendee' => $attendee,
        ], 200);
    }
}

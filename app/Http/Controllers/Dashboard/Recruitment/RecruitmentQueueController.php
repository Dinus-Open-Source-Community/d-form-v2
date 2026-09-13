<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentQueueEntry;
use App\Services\Recruitment\AttendanceService;
use App\Services\Recruitment\InterviewSessionService;
use App\Services\Recruitment\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentQueueController extends Controller
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly InterviewSessionService $sessionService,
        private readonly AttendanceService $attendanceService,
    ) {
    }

    public function show(RecruitmentInterviewSession $session): Response
    {
        $this->authorize('viewQueue', $session);

        return Inertia::render('Dashboard/Recruitment/Queue/Show', [
            'session' => $this->sessionService->toShowArray($session),
            'queue' => $this->queueService->snapshot($session),
            'pollUrl' => route('dashboard.recruitment.queue.poll', $session),
            'callNextUrl' => route('dashboard.recruitment.queue.call-next', $session),
            'completeUrlTemplate' => route('dashboard.recruitment.queue.complete', ['entry' => '__ENTRY__']),
            'canManage' => auth()->user()?->can('recruitment.queue.manage') === true,
        ]);
    }

    public function poll(RecruitmentInterviewSession $session): JsonResponse
    {
        $this->authorize('viewQueue', $session);

        return response()->json($this->queueService->snapshot($session));
    }

    public function callNext(RecruitmentInterviewSession $session): JsonResponse
    {
        $this->authorize('manageQueue', $session);

        $entry = $this->queueService->callNext($session);

        if ($entry === null) {
            return response()->json([
                'message' => __('No applicants waiting in queue.'),
                'queue' => $this->queueService->snapshot($session),
            ], 404);
        }

        return response()->json([
            'message' => __('Applicant called.'),
            'called' => [
                'id' => $entry->id,
                'queue_number' => $entry->queue_number,
                'application' => $entry->application ? [
                    'full_name' => $entry->application->full_name,
                    'registration_number' => $entry->application->registration_number,
                ] : null,
            ],
            'queue' => $this->queueService->snapshot($session),
        ]);
    }

    public function complete(RecruitmentQueueEntry $entry): JsonResponse
    {
        $entry->loadMissing('session');
        $session = $entry->session;

        if ($session === null) {
            abort(404);
        }

        $this->authorize('manageQueue', $session);

        $this->queueService->complete($entry);

        return response()->json([
            'message' => __('Queue entry marked as completed.'),
            'queue' => $this->queueService->snapshot($session),
        ]);
    }

    public function markNoShow(Request $request, RecruitmentInterviewSession $session): JsonResponse
    {
        $this->authorize('manageQueue', $session);

        $validated = $request->validate([
            'application_id' => ['required', 'uuid', 'exists:recruitment_applications,id'],
        ]);

        $application = \App\Models\Recruitment\RecruitmentApplication::query()->findOrFail($validated['application_id']);

        $this->attendanceService->markNoShow($application, $request->user(), $request);

        return response()->json([
            'message' => __('Applicant marked as no-show.'),
        ]);
    }
}

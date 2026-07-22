<?php

namespace App\Http\Controllers\Dashboard\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceScanStoreRequest;
use App\Jobs\RecordAttendanceJob;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Services\Attendance\AttendanceScanSubmissionResolver;
use App\Services\Event\EventService;
use App\Services\Registration\BundleGuestDisplayNameResolver;
use App\Services\Registration\FormAnswerRecipientResolver;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceScanController extends Controller
{
    public function show(Event $event, EventService $eventService): Response
    {
        $this->authorize('view', $event);

        return Inertia::render('Dashboard/Events/Scan', [
            'event' => $eventService->eventToInertiaArray($event),
            'attendanceScanStoreUrl' => route('dashboard.events.attendance-scan.store', $event),
        ]);
    }

    public function store(
        AttendanceScanStoreRequest $request,
        Event $event,
        AttendanceScanSubmissionResolver $resolver,
        BundleGuestDisplayNameResolver $displayNameResolver,
        FormAnswerRecipientResolver $recipientResolver,
    ): JsonResponse {
        $answer = $resolver->resolve(
            $event,
            $request->validated('submission_id'),
            $request->validated('registration_code'),
            $request->validated('raw_payload'),
        );

        $already = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('form_answer_id', $answer->id)
            ->exists();

        if ($already) {
            return response()->json([
                'message' => __('This participant has already checked in for this event.'),
                'attendee' => [
                    'name' => $displayNameResolver->resolve($answer),
                    'email' => $recipientResolver->email($answer) ?? '',
                    'form_answer_id' => $answer->id,
                ],
            ], 409);
        }

        RecordAttendanceJob::dispatch($event->id, $answer->id, $request->user()->id)->afterCommit();

        return response()->json([
            'message' => __('Check-in queued. A confirmation email will be sent when processing completes.'),
            'attendee' => [
                'name' => $displayNameResolver->resolve($answer),
                'email' => $recipientResolver->email($answer) ?? '',
                'form_answer_id' => $answer->id,
            ],
        ], 202);
    }
}

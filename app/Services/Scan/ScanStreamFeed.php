<?php

namespace App\Services\Scan;

use App\Models\EventAttendance;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Services\Registration\BundleGuestDisplayNameResolver;
use App\Services\Registration\FormAnswerRecipientResolver;
use Illuminate\Support\Carbon;

final class ScanStreamFeed
{
    public function __construct(
        private readonly BundleGuestDisplayNameResolver $displayNameResolver,
        private readonly FormAnswerRecipientResolver $recipientResolver,
    ) {
    }

    /**
     * @return array<int, array{id:string,ts:string,type:string,eventTitle:string,name:string,identifier:string,queueNumber:int|null}>
     */
    public function since(?string $cursorIso, int $limit = 50): array
    {
        $cursor = $cursorIso !== null ? Carbon::parse($cursorIso)->subSeconds(2) : null;
        $fetchLimit = $limit * 2;
        $rows = [];

        $eventQuery = EventAttendance::query()->with(['formAnswer.form.event', 'formAnswer.user'])->orderBy('scanned_at');
        if ($cursor !== null) {
            $eventQuery->where('scanned_at', '>=', $cursor);
        }
        foreach ($eventQuery->limit($fetchLimit)->get() as $attendance) {
            $answer = $attendance->formAnswer;
            if ($answer === null || $answer->form === null || $answer->form->event === null) {
                continue;
            }
            $rows[] = [
                'id' => 'evt:'.$attendance->form_answer_id.':'.$attendance->scanned_at->toIso8601String(),
                'ts' => $attendance->scanned_at->toIso8601String(),
                'type' => 'event',
                'eventTitle' => $answer->form->event->title,
                'name' => $this->displayNameResolver->resolve($answer),
                'identifier' => $this->recipientResolver->email($answer) ?? '',
                'queueNumber' => null,
            ];
        }

        $recQuery = RecruitmentAttendance::query()->orderBy('checked_in_at');
        if ($cursor !== null) {
            $recQuery->where('checked_in_at', '>=', $cursor);
        }
        $recAttendances = $recQuery->limit($fetchLimit)->get();
        $applications = RecruitmentApplication::query()
            ->with('queueEntry')
            ->whereIn('id', $recAttendances->pluck('recruitment_application_id')->unique()->values()->all())
            ->get()
            ->keyBy('id');
        $sessions = RecruitmentInterviewSession::query()
            ->with(['division:id,name', 'period:id,name'])
            ->whereIn('id', $recAttendances->pluck('recruitment_interview_session_id')->unique()->values()->all())
            ->get()
            ->keyBy('id');
        foreach ($recAttendances as $attendance) {
            $application = $applications->get($attendance->recruitment_application_id);
            if ($application === null) {
                continue;
            }
            $session = $sessions->get($attendance->recruitment_interview_session_id);
            $rows[] = [
                'id' => 'rec:'.$attendance->id,
                'ts' => $attendance->checked_in_at->toIso8601String(),
                'type' => 'recruitment',
                'eventTitle' => 'Oprec · '.($session?->division?->name ?? '').' · '.($session?->session_date ?? ''),
                'name' => $application->full_name,
                'identifier' => $application->registration_number,
                'queueNumber' => $application->queueEntry?->queue_number,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['ts'], $b['ts']));

        return array_slice(array_values($rows), 0, $limit);
    }
}

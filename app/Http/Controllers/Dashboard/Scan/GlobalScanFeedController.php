<?php

namespace App\Http\Controllers\Dashboard\Scan;

use App\Enums\Recruitment\QueueStatus;
use App\Http\Controllers\Controller;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\User;
use App\Services\Recruitment\InterviewSessionService;
use App\Services\Recruitment\QueueService;
use App\Services\Scan\ScanStreamFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class GlobalScanFeedController extends Controller
{
    private const WAITING_PREVIEW_LIMIT = 5;

    public function __construct(
        private readonly ScanStreamFeed $feed,
        private readonly QueueService $queueService,
        private readonly InterviewSessionService $sessionService,
    ) {
    }

    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ($user->can('events.list') || $user->can('recruitment.attendance.scan')),
            403
        );

        $since = $this->normalizeSince($request->query('since'));
        $rows = $this->feed->since($since);

        return response()->json([
            'rows' => $rows,
            'cursor' => $rows === [] ? $since : $rows[array_key_last($rows)]['ts'],
            'queue' => $this->queueSnapshot($user),
        ]);
    }

    private function normalizeSince(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{sessionId:string,label:string,nowServing:array{name:string,queueNumber:int}|null,waiting:list<array{name:string,queueNumber:int}>,waitingCount:int}>
     */
    private function queueSnapshot(User $user): array
    {
        if (! $user->can('recruitment.attendance.scan')) {
            return [];
        }

        $sessions = RecruitmentInterviewSession::query()
            ->where('is_active', true)
            ->whereDate('session_date', '>=', today())
            ->with(['division:id,name', 'period:id,name'])
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get();

        $snapshot = [];

        foreach ($sessions as $session) {
            $queue = $this->queueService->snapshot($session);
            $current = $queue['current'];
            $waitingCount = (int) $queue['stats']['waiting'];

            if ($current === null && $waitingCount === 0) {
                continue;
            }

            $waiting = array_values(array_filter(
                $queue['entries'],
                static fn (array $entry): bool => $entry['status'] === QueueStatus::Waiting->value,
            ));

            $snapshot[] = [
                'sessionId' => $session->id,
                'label' => $this->sessionLabel($session),
                'nowServing' => $current === null ? null : [
                    'name' => (string) ($current['application']['full_name'] ?? ''),
                    'queueNumber' => (int) $current['queue_number'],
                ],
                'waiting' => array_map(
                    static fn (array $entry): array => [
                        'name' => (string) ($entry['application']['full_name'] ?? ''),
                        'queueNumber' => (int) $entry['queue_number'],
                    ],
                    array_slice($waiting, 0, self::WAITING_PREVIEW_LIMIT),
                ),
                'waitingCount' => $waitingCount,
            ];
        }

        return $snapshot;
    }

    private function sessionLabel(RecruitmentInterviewSession $session): string
    {
        $parts = array_filter([
            $session->division?->name,
            $session->session_date?->toDateString(),
            $session->room,
        ]);

        return implode(' · ', $parts);
    }
}

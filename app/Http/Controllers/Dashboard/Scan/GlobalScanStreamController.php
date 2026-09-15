<?php

namespace App\Http\Controllers\Dashboard\Scan;

use App\Http\Controllers\Controller;
use App\Services\Scan\ScanStreamFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GlobalScanStreamController extends Controller
{
    public function __construct(
        private readonly ScanStreamFeed $feed,
    ) {
    }

    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ($user->can('events.list') || $user->can('recruitment.attendance.scan')),
            403
        );

        $cursor = $request->header('Last-Event-ID');
        if (! is_string($cursor) || $cursor === '') {
            $queryCursor = $request->query('cursor');
            $cursor = is_string($queryCursor) && $queryCursor !== '' ? $queryCursor : null;
        }
        $cursorIso = $this->toCursorIso($cursor);
        $feed = $this->feed;

        return response()->stream(function () use ($feed, $cursorIso): void {
            ignore_user_abort(true);
            set_time_limit(0);

            echo "retry: 3000\n\n";
            flush();

            $cursor = $cursorIso;
            $sentIds = [];
            $deadline = time() + 120;
            $lastHeartbeat = time();

            while (time() < $deadline) {
                if (connection_aborted() !== 0) {
                    break;
                }

                foreach ($feed->since($cursor) as $row) {
                    if (isset($sentIds[$row['id']])) {
                        continue;
                    }
                    $sentIds[$row['id']] = true;
                    $cursor = $row['ts'];
                    echo 'id: '.$row['id']."\n";
                    echo "event: scan\n";
                    echo 'data: '.json_encode($row)."\n\n";
                    flush();

                    if (connection_aborted() !== 0) {
                        break 2;
                    }
                }

                if (time() - $lastHeartbeat >= 15) {
                    echo ": ping\n\n";
                    flush();
                    $lastHeartbeat = time();
                }

                sleep(2);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function toCursorIso(?string $cursor): ?string
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $parts = explode(':', $cursor, 3);
        $candidate = $parts[2] ?? null;
        if ($candidate === null || $candidate === '') {
            return null;
        }

        try {
            return Carbon::parse($candidate)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}

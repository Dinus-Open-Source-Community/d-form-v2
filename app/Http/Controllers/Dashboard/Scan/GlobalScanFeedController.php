<?php

namespace App\Http\Controllers\Dashboard\Scan;

use App\Http\Controllers\Controller;
use App\Services\Scan\ScanStreamFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class GlobalScanFeedController extends Controller
{
    public function __construct(
        private readonly ScanStreamFeed $feed,
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
}

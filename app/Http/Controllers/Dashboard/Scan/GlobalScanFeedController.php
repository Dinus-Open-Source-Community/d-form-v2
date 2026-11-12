<?php

namespace App\Http\Controllers\Dashboard\Scan;

use App\Http\Controllers\Controller;
use App\Services\Scan\ScanStreamFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GlobalScanFeedController extends Controller
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 100;

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

        try {
            $since = $this->normalizeSince($request->query('since'));
            $limit = $this->normalizeLimit($request->query('limit'));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $page = $this->feed->page($since, $limit);

        return response()->json([
            'rows' => $page['rows'],
            'cursor' => $page['cursor'] ?? $since,
        ]);
    }

    /**
     * Token opaque `ts|eventId|recruitmentId`; string ISO lama tetap diterima
     * sebagai "ts tanpa id". Nilai yang tidak bisa diparse ditolak agar feed
     * tidak diam-diam memutar ulang seluruh riwayat.
     *
     * @throws InvalidArgumentException
     */
    private function normalizeSince(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Invalid feed cursor.');
        }

        $normalized = ScanStreamFeed::normalizeCursor($value);

        if ($normalized === null) {
            throw new InvalidArgumentException('Invalid feed cursor.');
        }

        return $normalized;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function normalizeLimit(mixed $value): int
    {
        if ($value === null || $value === '') {
            return self::DEFAULT_LIMIT;
        }

        if (! is_string($value) || preg_match('/^\d+$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid feed limit.');
        }

        return max(1, min(self::MAX_LIMIT, (int) $value));
    }
}

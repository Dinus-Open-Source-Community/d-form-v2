<?php

namespace App\Http\Controllers\Dashboard\Du;

use App\Http\Controllers\Controller;
use App\Services\Du\DuCourseOption;
use App\Services\Du\DuCourseSyncService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class DuCourseSyncController extends Controller
{
    public function __invoke(DuCourseSyncService $service): JsonResponse
    {
        try {
            $courses = $service->sync();
        } catch (QueryException $exception) {
            // QueryException extends RuntimeException but a local DB failure is not an upstream one.
            throw $exception;
        } catch (RuntimeException) {
            return response()->json(['message' => 'Katalog kelas DU tidak dapat dimuat.'], 502);
        }

        return response()->json([
            'courses' => array_map(
                static fn (DuCourseOption $course): array => ['uid' => $course->uid, 'title' => $course->title],
                $courses,
            ),
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}

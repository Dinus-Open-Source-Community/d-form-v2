<?php

namespace App\Http\Controllers\Dashboard\Du;

use App\Http\Controllers\Controller;
use App\Models\DuCourse;
use App\Services\Du\DuCourseRepository;
use Illuminate\Http\JsonResponse;

final class DuCourseListController extends Controller
{
    /** Reads the local `du_courses` cache only; never calls the DU API. */
    public function __invoke(DuCourseRepository $courses): JsonResponse
    {
        return response()->json([
            'courses' => $courses->active()
                ->map(static fn (DuCourse $course): array => [
                    'uid' => $course->uid,
                    'title' => $course->title,
                ])->values()->all(),
        ]);
    }
}

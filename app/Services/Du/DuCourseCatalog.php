<?php

namespace App\Services\Du;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class DuCourseCatalog
{
    /** @return list<DuCourseOption> */
    public function activeCourses(): array
    {
        $courses = [];
        $page = 1;
        $totalPages = 1;

        while ($page <= $totalPages) {
            try {
                $response = $this->request()->get($this->url('/api/courses'), [
                    'page' => $page, 'per_page' => 100, 'status' => 'ACTIVE',
                ]);
            } catch (\Throwable $exception) {
                Log::warning('DU catalog request failed', ['exception' => $exception::class]);
                throw new RuntimeException('DU course catalog unavailable.', 0, $exception);
            }

            if (!$response->successful()) {
                Log::warning('DU catalog rejected', ['status' => $response->status()]);
                throw new RuntimeException('DU course catalog unavailable.');
            }

            $payload = $response->json();
            if (!is_array($payload) || ($payload['success'] ?? false) !== true || !is_array($payload['data'] ?? null)) {
                $this->rejectInvalidPayload($page);
            }
            $data = $payload['data'];
            if (!is_array($data['courses'] ?? null) || !is_array($data['meta'] ?? null) || !is_int($data['meta']['total_pages'] ?? null)) {
                $this->rejectInvalidPayload($page);
            }
            $totalPages = $data['meta']['total_pages'];
            if ($totalPages < 1) {
                $this->rejectInvalidPayload($page);
            }
            foreach ($data['courses'] as $course) {
                if (
                    !is_array($course)
                    || !is_string($course['uid'] ?? null)
                    || !is_string($course['title'] ?? null)
                    || !is_string($course['status'] ?? null)
                    || trim($course['uid']) === ''
                    || trim($course['title']) === ''
                ) {
                    $this->rejectInvalidPayload($page);
                }
                if ($course['status'] === 'ACTIVE') {
                    $courses[] = new DuCourseOption($course['uid'], $course['title'], $course['status']);
                }
            }
            $page++;
        }
        return $courses;
    }

    /** Logs a payload-shape rejection without payload values, then aborts the sync. */
    private function rejectInvalidPayload(int $page): never
    {
        Log::warning('DU catalog response invalid', ['page' => $page]);

        throw new RuntimeException('Invalid DU course catalog response.');
    }

    private function request(): PendingRequest
    {
        return Http::timeout((int) config('services.du.timeout', 10))->acceptJson();
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.du.base_url'), '/') . '/' . ltrim($path, '/');
    }
}

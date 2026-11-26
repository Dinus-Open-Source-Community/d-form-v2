<?php

namespace Tests\Feature\Forms;

use App\Services\Du\DuCourseCatalog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class DuCourseCatalogTest extends TestCase
{
    public function test_loads_pages_and_filters_active_courses(): void
    {
        config(['services.du.base_url' => 'https://du.example.test/']);
        config(['services.du.api_key' => 'secret']);
        Http::fakeSequence()->push(['success' => true, 'data' => ['courses' => [['uid' => 'a', 'title' => 'A', 'status' => 'ACTIVE'], ['uid' => 'i', 'title' => 'I', 'status' => 'INACTIVE']], 'meta' => ['total_pages' => 2]]])->push(['success' => true, 'data' => ['courses' => [['uid' => 'b', 'title' => 'B', 'status' => 'ACTIVE']], 'meta' => ['total_pages' => 2]]]);
        $courses = (new DuCourseCatalog())->activeCourses();
        $this->assertSame(['a', 'b'], array_column($courses, 'uid'));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://du.example.test/api/courses?page=1&per_page=100&status=ACTIVE' && $request->header('X-API-Key') === []);
    }

    public function test_rejects_malformed_catalog(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['courses' => [['uid' => 'x']]]])]);
        $this->expectException(RuntimeException::class);
        (new DuCourseCatalog())->activeCourses();
    }

    public function test_rejects_non_positive_total_pages(): void
    {
        foreach ([0, -1] as $totalPages) {
            Http::fake(['*' => Http::response(['success' => true, 'data' => ['courses' => [], 'meta' => ['total_pages' => $totalPages]]])]);
            try {
                (new DuCourseCatalog())->activeCourses();
                $this->fail('Expected malformed catalog exception.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Invalid DU course catalog response.', $exception->getMessage());
            }
        }
    }

    public function test_rejects_empty_course_uid(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['courses' => [
            ['uid' => '', 'title' => 'Kelas A', 'status' => 'ACTIVE'],
        ], 'meta' => ['total_pages' => 1]]])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid DU course catalog response.');
        (new DuCourseCatalog())->activeCourses();
    }

    public function test_rejects_whitespace_only_course_title(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['courses' => [
            ['uid' => 'course-a', 'title' => '   ', 'status' => 'ACTIVE'],
        ], 'meta' => ['total_pages' => 1]]])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid DU course catalog response.');
        (new DuCourseCatalog())->activeCourses();
    }

    public function test_invalid_payload_is_logged_without_payload_values(): void
    {
        Log::spy();
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['courses' => [
            ['uid' => 'secret-payload-marker'],
        ]]])]);

        try {
            (new DuCourseCatalog())->activeCourses();
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Invalid DU course catalog response.', $exception->getMessage());
        }

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context): bool => $message === 'DU catalog response invalid'
                && !str_contains(json_encode($context), 'secret-payload-marker'),
        );
    }

    public function test_maps_connection_exception_to_safe_runtime_exception(): void
    {
        Http::fake(fn (): never => throw new ConnectionException('timeout with secret-payload-marker'));

        try {
            (new DuCourseCatalog())->activeCourses();
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
            $this->assertSame('DU course catalog unavailable.', $exception->getMessage());
            $this->assertStringNotContainsString('secret-payload-marker', $exception->getMessage());
        }
    }
}

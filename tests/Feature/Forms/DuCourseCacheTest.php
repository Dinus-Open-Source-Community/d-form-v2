<?php

namespace Tests\Feature\Forms;

use App\Models\DuCourse;
use App\Services\Du\DuCourseOption;
use App\Services\Du\DuCourseRepository;
use App\Services\Du\DuCourseSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DuCourseCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_replace_upserts_active_courses_and_deactivates_missing_rows(): void
    {
        DuCourse::factory()->create(['uid' => 'old', 'title' => 'Old', 'is_active' => true]);
        app(DuCourseRepository::class)->replace([
            new DuCourseOption('course-a', 'Kelas A', 'ACTIVE'),
            new DuCourseOption('course-b', 'Kelas B', 'ACTIVE'),
        ]);

        $this->assertDatabaseHas('du_courses', ['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        $this->assertDatabaseHas('du_courses', ['uid' => 'course-b', 'is_active' => true]);
        $this->assertDatabaseHas('du_courses', ['uid' => 'old', 'is_active' => false]);
        $this->assertSame(['Kelas A', 'Kelas B'], app(DuCourseRepository::class)->active()->pluck('title')->all());

        app(DuCourseRepository::class)->replace([
            new DuCourseOption('course-a', 'Kelas A Updated', 'ACTIVE'),
            new DuCourseOption('course-b', 'Kelas B', 'ACTIVE'),
        ]);
        $this->assertDatabaseHas('du_courses', ['uid' => 'course-a', 'title' => 'Kelas A Updated', 'is_active' => true]);
        $this->assertSame(3, DuCourse::query()->count());
    }

    public function test_active_selectable_values_contain_uids_and_titles_only_for_active_rows(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Kelas B', 'is_active' => false]);
        $values = app(DuCourseRepository::class)->activeSelectableValues();

        $this->assertContains('course-a', $values);
        $this->assertContains('Kelas A', $values);
        $this->assertNotContains('course-b', $values);
        $this->assertNotContains('Kelas B', $values);
    }

    public function test_sync_reads_catalog_once_and_sync_if_empty_skips_when_rows_exist(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['courses' => [
            ['uid' => 'course-a', 'title' => 'Kelas A', 'status' => 'ACTIVE'],
        ], 'meta' => ['total_pages' => 1]]])]);

        DuCourse::factory()->create(['uid' => 'cached', 'title' => 'Cached', 'is_active' => true]);

        $this->assertNull(app(DuCourseSyncService::class)->syncIfEmpty());
        Http::assertNothingSent();

        $this->assertCount(1, app(DuCourseSyncService::class)->sync());
        Http::assertSentCount(1);
        $this->assertDatabaseHas('du_courses', ['uid' => 'cached', 'is_active' => false]);
    }
}

<?php

namespace Tests\Feature\Forms;

use App\Models\DuCourse;
use App\Services\Du\DuCourseRepository;
use App\Services\Du\DuCourseResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DuCourseResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_exact_uid_and_title_and_rejects_ambiguity(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-c', 'title' => 'Kelas C', 'is_active' => false]);

        $resolver = app(DuCourseResolver::class);

        $this->assertSame('course-a', $resolver->activeUidFor('course-a'));
        $this->assertNull($resolver->activeUidFor('Kelas A'));
        $this->assertNull($resolver->activeUidFor('Kelas C'));
        $this->assertSame('course-c', $resolver->uidForIssuance('Kelas C'));
        $this->assertNull($resolver->uidForIssuance('Kelas Tidak Ada'));
    }

    public function test_repository_title_lookup_needs_exactly_one_match(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Kelas B', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-c', 'title' => 'Kelas B', 'is_active' => true]);

        $repository = app(DuCourseRepository::class);

        $this->assertSame('course-a', $repository->findUniqueByValue('Kelas A', true)?->uid);
        $this->assertNull($repository->findUniqueByValue('Kelas B', true));
        $this->assertSame('course-b', $repository->findUniqueByValue('course-b', true)?->uid);
    }

    public function test_batch_resolution_maps_mixed_values_with_one_query(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Kelas B', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-c', 'title' => 'Kelas B', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-d', 'title' => 'Kelas D', 'is_active' => false]);

        DB::enableQueryLog();
        $resolved = app(DuCourseResolver::class)->activeUidsFor(['course-a', 'Kelas A', 'Kelas B', 'Kelas D', 'Kelas Basi']);
        $courseQueries = collect(DB::getQueryLog())->filter(
            static fn (array $query): bool => str_contains($query['query'], 'du_courses'),
        );
        DB::disableQueryLog();

        $this->assertSame([
            'course-a' => 'course-a',
            'Kelas A' => 'course-a',
            'Kelas B' => null,
            'Kelas D' => null,
            'Kelas Basi' => null,
        ], $resolved);
        $this->assertCount(1, $courseQueries);
    }
}

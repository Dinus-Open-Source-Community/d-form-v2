<?php

namespace Tests\Feature\E2e;

use App\Models\DuCourse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Kontrak `e2e:du-course` yang dipakai spec Playwright. */
final class DuCourseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_guard_blocks_command_without_writing_rows(): void
    {
        $this->app['env'] = 'production';

        try {
            $exit = Artisan::call('e2e:du-course', ['--uid' => 'e2e-du-golang', '--title' => 'Golang E2E']);
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(1, $exit);
        $this->assertSame(0, DuCourse::query()->count());
    }

    public function test_upsert_requires_uid_and_title_without_clear(): void
    {
        $this->assertSame(1, Artisan::call('e2e:du-course'));
        $this->assertStringContainsString('--uid dan --title wajib diisi', Artisan::output());
        $this->assertSame(0, DuCourse::query()->count());
    }

    public function test_clear_rejects_empty_and_non_e2e_prefix(): void
    {
        DuCourse::factory()->create(['uid' => 'other-course']);

        $this->assertSame(1, Artisan::call('e2e:du-course', ['--clear' => '']));
        $this->assertStringContainsString('diawali "e2e-"', Artisan::output());

        $this->assertSame(1, Artisan::call('e2e:du-course', ['--clear' => 'other-']));
        $this->assertSame(1, DuCourse::query()->count());
    }

    public function test_clear_cannot_be_combined_with_uid(): void
    {
        DuCourse::factory()->create(['uid' => 'e2e-du-golang']);

        $exit = Artisan::call('e2e:du-course', ['--clear' => 'e2e-du-', '--uid' => 'e2e-du-sql']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('tidak boleh digabung', Artisan::output());
        $this->assertSame(1, DuCourse::query()->count());
    }

    public function test_upsert_of_same_uid_keeps_one_active_row(): void
    {
        $this->assertSame(0, Artisan::call('e2e:du-course', [
            '--uid' => 'e2e-du-golang',
            '--title' => 'Golang E2E',
        ]));
        $created = DuCourse::query()->where('uid', 'e2e-du-golang')->sole();

        $this->assertSame(0, Artisan::call('e2e:du-course', [
            '--uid' => 'e2e-du-golang',
            '--title' => 'Golang E2E v2',
        ]));
        $updated = DuCourse::query()->where('uid', 'e2e-du-golang')->sole();

        $this->assertSame(1, DuCourse::query()->count());
        $this->assertSame($created->id, $updated->id);
        $this->assertSame('Golang E2E v2', $updated->title);
        $this->assertTrue($updated->is_active);
    }

    public function test_clear_deletes_only_rows_matching_prefix(): void
    {
        DuCourse::factory()->create(['uid' => 'e2e-du-golang']);
        DuCourse::factory()->create(['uid' => 'e2e-du-sql']);
        DuCourse::factory()->create(['uid' => 'live-course']);

        $this->assertSame(0, Artisan::call('e2e:du-course', ['--clear' => 'e2e-du-']));

        $this->assertSame(0, DuCourse::query()->where('uid', 'like', 'e2e-du-%')->count());
        $this->assertSame(1, DuCourse::query()->where('uid', 'live-course')->count());
    }
}

<?php

namespace Tests\Feature\Reporting;

use App\Models\Event;
use App\Models\User;
use App\Services\Event\EventService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cache daftar event admin harus tetap bekerja, dan kegagalan query di dalam
 * resolver tidak boleh ditelan lalu diulang oleh fallback cache.
 */
class EventListCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_pemanggilan_kedua_memakai_cache_tanpa_query(): void
    {
        $user = $this->superAdmin();
        Event::factory()->count(2)->create();

        $service = app(EventService::class);
        $service->paginateForAdminIndex($this->listInput(), 1, $user);

        DB::enableQueryLog();
        $service->paginateForAdminIndex($this->listInput(), 1, $user);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }

    public function test_error_dari_resolver_tidak_ditelan_dan_tidak_diulang(): void
    {
        $service = app(EventService::class);
        $rememberList = new \ReflectionMethod($service, 'rememberList');

        $runCount = 0;
        $resolver = function () use (&$runCount): never {
            $runCount++;

            throw new \RuntimeException('query gagal');
        };

        try {
            $rememberList->invoke($service, 'list-page:events:kegagalan', $resolver);
            $this->fail('Error resolver seharusnya diteruskan ke pemanggil.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('query gagal', $exception->getMessage());
        }

        $this->assertSame(1, $runCount);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    /**
     * Input default paginasi daftar event admin; override dipakai untuk variasi sort/filter.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function listInput(array $overrides = []): array
    {
        return array_merge([
            'search' => '',
            'filter' => [
                'categories' => [],
                'sessions' => [],
                'statuses' => [],
                'showTrashed' => false,
                'timeline' => 'all',
            ],
            'sort' => ['by' => 'title', 'order' => 'asc'],
            'per_page' => 8,
        ], $overrides);
    }
}

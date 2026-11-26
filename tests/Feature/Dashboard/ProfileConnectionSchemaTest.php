<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Services\User\UserManagementService;
use App\Support\Database\UniqueConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ProfileConnectionSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION_PATH = 'migrations/2026_11_18_000001_add_unique_index_to_users_social_ids.php';

    public function test_soft_deleted_user_releases_provider_ids(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-stale',
            'github_id' => 'github-stale',
        ]);

        app(UserManagementService::class)->delete($user);

        $this->assertSoftDeleted('users', ['id' => $user->getKey()]);

        $row = DB::table('users')->where('id', $user->getKey())->first();
        $this->assertNull($row?->google_id);
        $this->assertNull($row?->github_id);

        // Provider id yang sudah dilepas tidak boleh memblokir akun lain.
        $replacement = User::factory()->create(['google_id' => 'google-stale']);
        $this->assertSame('google-stale', $replacement->google_id);
    }

    public function test_provider_ids_are_unique_across_users(): void
    {
        User::factory()->create(['google_id' => 'shared-google']);
        $this->assertUniqueViolation(
            fn () => User::factory()->create(['google_id' => 'shared-google']),
        );

        User::factory()->create(['github_id' => 'shared-github']);
        $this->assertUniqueViolation(
            fn () => User::factory()->create(['github_id' => 'shared-github']),
        );
    }

    public function test_migration_preflight_reports_duplicates(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_google_id_unique');
        });

        User::factory()->count(2)->create(['google_id' => 'dup-a']);
        User::factory()->count(2)->create(['google_id' => 'dup-b']);

        $migration = require database_path(self::MIGRATION_PATH);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('users.google_id has 2 duplicated values');

        $migration->up();
    }

    private function assertUniqueViolation(callable $write): void
    {
        try {
            $write();
        } catch (QueryException $exception) {
            $this->assertTrue(
                UniqueConstraintViolation::isViolation($exception),
                'Penulisan duplikat harus ditolak unique index.',
            );

            return;
        }

        $this->fail('Penulisan duplikat seharusnya ditolak unique index.');
    }
}

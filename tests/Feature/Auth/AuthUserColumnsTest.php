<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Query pemulihan user dari sesi tidak boleh memakai `select *` pada tabel users.
 */
class AuthUserColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_memuat_user_dengan_kolom_eksplisit(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();

        DB::enableQueryLog();
        $retrieved = Auth::guard('web')->getProvider()->retrieveById($user->id);
        $sql = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
        DB::disableQueryLog();

        $this->assertNotNull($retrieved);
        $this->assertSame($user->email, $retrieved->email);
        $this->assertStringContainsString('"email_verified_at"', $sql);
        $this->assertStringContainsString('"avatar"', $sql);
        $this->assertStringNotContainsString('select *', $sql);
        // Password tidak pernah diambil; hanya boolean hasil hitung di SQL.
        $this->assertStringNotContainsString('"password"', $sql);
        $this->assertStringContainsString('has_local_password', $sql);
        $this->assertTrue($retrieved->has_local_password);
        // Kolom alur khusus tidak ikut di setiap request.
        $this->assertStringNotContainsString('"google_id"', $sql);
        $this->assertStringNotContainsString('"remember_token"', $sql);
    }

    public function test_remember_me_memuat_remember_token(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->setRememberToken('token-uji');
        $user->save();

        DB::enableQueryLog();
        $retrieved = Auth::guard('web')->getProvider()->retrieveByToken($user->id, 'token-uji');
        $sql = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
        DB::disableQueryLog();

        $this->assertNotNull($retrieved);
        $this->assertStringContainsString('"remember_token"', $sql);
        $this->assertStringNotContainsString('select *', $sql);
    }

    public function test_request_setelah_login_tidak_select_semua_kolom_users(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->post('/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        // Guard menyimpan instance user setelah login; paksa resolusi ulang dari sesi.
        $this->app['auth']->forgetGuards();

        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $userQueries = $queries->filter(fn (string $query): bool => str_contains($query, 'from "users"'));

        $this->assertNotEmpty($userQueries);

        foreach ($userQueries as $query) {
            $this->assertStringNotContainsString('select *', $query);
            $this->assertStringNotContainsString('"password"', $query);
        }
    }
}

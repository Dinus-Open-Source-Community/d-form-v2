<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * EP-04 AUDIT-P1-005 F4: the generated normalized email column must stay in
 * sync on every write and be indexable on all supported engines.
 */
class UserEmailNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalized_column_follows_email_writes(): void
    {
        $user = User::factory()->create(['email' => 'MiXeD.Case@Example.COM']);

        $this->assertSame(
            'mixed.case@example.com',
            DB::table('users')->where('id', $user->id)->value('email_normalized'),
        );

        $user->email = 'Second.Mixed@Example.COM';
        $user->save();

        $this->assertSame(
            'second.mixed@example.com',
            DB::table('users')->where('id', $user->id)->value('email_normalized'),
        );
    }

    public function test_normalized_email_index_exists(): void
    {
        $this->assertTrue(Schema::hasIndex('users', 'users_email_normalized_index'));
    }

    public function test_normalized_column_is_hidden_from_serialization(): void
    {
        $user = User::factory()->create(['email' => 'Hidden.Column@Example.com']);

        $this->assertArrayNotHasKey('email_normalized', $user->toArray());
    }
}

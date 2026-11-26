<?php

namespace Tests\Feature\Auth;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Models\UserPinCredential;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Skema keamanan akun (spec §6.1): tantangan verifikasi email dan kredensial PIN satu baris per user. */
class AuthSecuritySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_codes_table_has_the_designed_columns(): void
    {
        $this->assertTrue(Schema::hasTable('email_verification_codes'));

        foreach ([
            'id',
            'user_id',
            'code_hash',
            'expires_at',
            'attempts',
            'sends',
            'last_sent_at',
            'locked_until',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('email_verification_codes', $column),
                'Kolom email_verification_codes.'.$column.' tidak ada.',
            );
        }
    }

    public function test_email_verification_codes_never_stores_a_plaintext_code_column(): void
    {
        $this->assertFalse(Schema::hasColumn('email_verification_codes', 'code'));
        $this->assertFalse(Schema::hasColumn('email_verification_codes', 'otp_code'));
    }

    public function test_email_verification_codes_user_id_is_unique(): void
    {
        $index = collect(Schema::getIndexes('email_verification_codes'))
            ->first(fn (array $index): bool => $index['columns'] === ['user_id']);

        $this->assertNotNull($index, 'email_verification_codes.user_id tidak memiliki index.');
        $this->assertTrue((bool) $index['unique'], 'email_verification_codes.user_id harus unik.');
    }

    public function test_email_verification_codes_foreign_key_cascades_from_users(): void
    {
        $foreignKey = collect(Schema::getForeignKeys('email_verification_codes'))
            ->first(fn (array $foreignKey): bool => $foreignKey['columns'] === ['user_id']);

        $this->assertNotNull($foreignKey, 'email_verification_codes.user_id tidak memiliki foreign key.');
        $this->assertSame('users', $foreignKey['foreign_table']);
        $this->assertSame('cascade', strtolower((string) $foreignKey['on_delete']));

        $user = User::factory()->unverified()->create();
        EmailVerificationCode::create([
            'user_id' => $user->getKey(),
            'code_hash' => str_repeat('a', 64),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->getKey()]);

        // User memakai soft delete; cascade hanya terbukti lewat penghapusan permanen.
        $user->forceDelete();

        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->getKey()]);
    }

    public function test_email_verification_codes_rejects_a_second_row_for_the_same_user(): void
    {
        $user = User::factory()->unverified()->create();

        EmailVerificationCode::create([
            'user_id' => $user->getKey(),
            'code_hash' => str_repeat('a', 64),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->expectException(QueryException::class);

        EmailVerificationCode::create([
            'user_id' => $user->getKey(),
            'code_hash' => str_repeat('b', 64),
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function test_user_pin_credentials_table_has_the_designed_columns(): void
    {
        $this->assertTrue(Schema::hasTable('user_pin_credentials'));

        foreach ([
            'id',
            'user_id',
            'pin_hash',
            'failed_attempts',
            'locked_until',
            'last_verified_at',
            'enabled_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('user_pin_credentials', $column),
                'Kolom user_pin_credentials.'.$column.' tidak ada.',
            );
        }

        $this->assertFalse(Schema::hasColumn('user_pin_credentials', 'pin'));
    }

    public function test_user_pin_credentials_user_id_is_unique_and_cascades_from_users(): void
    {
        $index = collect(Schema::getIndexes('user_pin_credentials'))
            ->first(fn (array $index): bool => $index['columns'] === ['user_id']);

        $this->assertNotNull($index, 'user_pin_credentials.user_id tidak memiliki index.');
        $this->assertTrue((bool) $index['unique'], 'user_pin_credentials.user_id harus unik.');

        $foreignKey = collect(Schema::getForeignKeys('user_pin_credentials'))
            ->first(fn (array $foreignKey): bool => $foreignKey['columns'] === ['user_id']);

        $this->assertNotNull($foreignKey, 'user_pin_credentials.user_id tidak memiliki foreign key.');
        $this->assertSame('users', $foreignKey['foreign_table']);
        $this->assertSame('cascade', strtolower((string) $foreignKey['on_delete']));

        $user = User::factory()->create();
        UserPinCredential::create([
            'user_id' => $user->getKey(),
            'pin_hash' => 'hash-pin',
            'enabled_at' => now(),
        ]);

        $user->forceDelete();

        $this->assertDatabaseMissing('user_pin_credentials', ['user_id' => $user->getKey()]);
    }

    public function test_models_hide_the_secret_columns_from_serialization(): void
    {
        $challenge = new EmailVerificationCode(['code_hash' => 'rahasia']);
        $credential = new UserPinCredential(['pin_hash' => 'rahasia']);

        $this->assertArrayNotHasKey('code_hash', $challenge->toArray());
        $this->assertArrayNotHasKey('pin_hash', $credential->toArray());
    }
}

<?php

namespace Tests\Feature\Forms;

use App\Models\Form;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Membuktikan proteksi duplikat ada di level database, bukan hanya di FormAccessGuard.
 *
 * Test ini menulis langsung ke tabel agar tidak bisa lolos karena lapisan aplikasi.
 * Di SQLite/PostgreSQL constraint datang dari partial unique index; di MySQL dari
 * generated column + unique index (migrasi 2026_11_03_000001).
 */
class FormAnswerDuplicateConstraintTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function insertAnswer(array $overrides = []): bool
    {
        try {
            DB::table('form_answers')->insert(array_merge([
                'id' => (string) Str::orderedUuid(),
                'answers' => json_encode([]),
                'review_status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ], $overrides));
        } catch (QueryException) {
            return false;
        }

        return true;
    }

    public function test_active_answer_for_same_user_and_form_is_rejected_by_database(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create();

        $this->assertTrue($this->insertAnswer([
            'user_id' => (string) $user->id,
            'form_id' => (string) $form->id,
        ]));

        $this->assertFalse($this->insertAnswer([
            'user_id' => (string) $user->id,
            'form_id' => (string) $form->id,
        ]), 'Database must reject a second active registration for the same user and form.');

        $this->assertSame(1, DB::table('form_answers')->count());
    }

    public function test_rejected_answer_does_not_block_a_new_active_answer(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create();

        $this->assertTrue($this->insertAnswer([
            'user_id' => (string) $user->id,
            'form_id' => (string) $form->id,
            'review_status' => 'rejected',
        ]));

        $this->assertTrue($this->insertAnswer([
            'user_id' => (string) $user->id,
            'form_id' => (string) $form->id,
        ]));

        $this->assertSame(2, DB::table('form_answers')->count());
    }

    public function test_rejected_or_expired_member_answer_does_not_block_a_new_answer(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create();

        foreach (['rejected', 'expired'] as $status) {
            $this->assertTrue($this->insertAnswer([
                'user_id' => (string) $user->id,
                'form_id' => (string) $form->id,
                'registration_role' => 'member',
                'member_confirmation_status' => $status,
            ]));
        }

        $this->assertTrue($this->insertAnswer([
            'user_id' => (string) $user->id,
            'form_id' => (string) $form->id,
        ]));

        $this->assertSame(3, DB::table('form_answers')->count());
    }

    public function test_guest_answer_with_same_invited_email_is_rejected_by_database(): void
    {
        $form = Form::factory()->create();

        $this->assertTrue($this->insertAnswer([
            'user_id' => null,
            'form_id' => (string) $form->id,
            'invited_email' => 'guest@example.test',
        ]));

        $this->assertFalse($this->insertAnswer([
            'user_id' => null,
            'form_id' => (string) $form->id,
            'invited_email' => 'guest@example.test',
        ]), 'Database must reject a second guest answer for the same invited email.');

        $this->assertTrue($this->insertAnswer([
            'user_id' => null,
            'form_id' => (string) $form->id,
            'invited_email' => 'other@example.test',
        ]));

        $this->assertSame(2, DB::table('form_answers')->count());
    }
}

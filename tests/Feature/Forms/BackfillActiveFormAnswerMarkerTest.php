<?php

namespace Tests\Feature\Forms;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillActiveFormAnswerMarkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_backfills_active_rows_and_leaves_inactive_rows_null(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create();

        $activeId = $this->insertAnswer($user->id, $form->id, 'pending');
        $rejectedId = $this->insertAnswer($user->id, $form->id, 'rejected');

        $this->artisan('form-answers:backfill-active-marker', ['--chunk' => 1])
            ->expectsOutputToContain('Rows scanned:')
            ->expectsOutputToContain('Rows changed:')
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('form_answers')->where('id', $activeId)->value('is_active'));
        $this->assertNull(DB::table('form_answers')->where('id', $rejectedId)->value('is_active'));

        $this->artisan('form-answers:backfill-active-marker', ['--chunk' => 1])
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('form_answers')->where('id', $activeId)->value('is_active'));
        $this->assertNull(DB::table('form_answers')->where('id', $rejectedId)->value('is_active'));
    }

    public function test_form_answer_lifecycle_keeps_marker_in_sync(): void
    {
        $answer = FormAnswer::factory()->create(['review_status' => 'rejected']);

        $this->assertNull($answer->is_active);

        $answer->review_status = 'accepted';
        $answer->save();

        $this->assertSame(1, $answer->fresh()?->is_active);

        $answer->review_status = 'rejected';
        $answer->save();

        $this->assertNull($answer->fresh()?->is_active);
    }

    public function test_contract_migration_aborts_before_backfill_is_complete(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create();

        $this->insertAnswer($user->id, $form->id, 'pending');

        $migration = require database_path(
            'migrations/2026_11_04_000002_create_active_form_answer_indexes.php'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('form-answers:backfill-active-marker');

        $migration->up();
    }

    private function insertAnswer(string $userId, string $formId, ?string $reviewStatus): string
    {
        $id = (string) Str::orderedUuid();

        DB::table('form_answers')->insert([
            'id' => $id,
            'answers' => json_encode([]),
            'user_id' => $userId,
            'form_id' => $formId,
            'review_status' => $reviewStatus,
            'is_active' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}

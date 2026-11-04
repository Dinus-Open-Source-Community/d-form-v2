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

    public function test_audit_reports_duplicate_groups_and_unmarked_active_rows_without_writes(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create();
        $guestEmail = 'audit-guest@example.test';

        $this->insertAnswer($user->id, $form->id, 'pending');
        $this->insertAnswer($user->id, $form->id, 'pending');
        $this->insertAnswer(null, $form->id, 'pending', ['invited_email' => $guestEmail]);
        $this->insertAnswer(null, $form->id, 'pending', ['invited_email' => $guestEmail]);
        $beforeCount = DB::table('form_answers')->count();

        $this->artisan('form-answers:audit-active-uniqueness')
            ->expectsOutputToContain('Active user duplicate groups: 1')
            ->expectsOutputToContain('Active guest duplicate groups: 1')
            ->expectsOutputToContain('Unmarked active rows: 4')
            ->assertExitCode(1);

        $this->assertSame($beforeCount, DB::table('form_answers')->count());
        $this->assertSame(4, DB::table('form_answers')->whereNull('is_active')->count());
    }

    public function test_audit_passes_after_marker_backfill(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create();

        $this->insertAnswer($user->id, $form->id, 'pending');

        $this->artisan('form-answers:backfill-active-marker')
            ->assertExitCode(0);

        $this->artisan('form-answers:audit-active-uniqueness')
            ->expectsOutputToContain('Active user duplicate groups: 0')
            ->expectsOutputToContain('Active guest duplicate groups: 0')
            ->expectsOutputToContain('Unmarked active rows: 0')
            ->assertExitCode(0);
    }

    /** @param array<string, mixed> $overrides */
    private function insertAnswer(?string $userId, string $formId, ?string $reviewStatus, array $overrides = []): string
    {
        $id = (string) Str::orderedUuid();

        DB::table('form_answers')->insert(array_merge([
            'id' => $id,
            'answers' => json_encode([]),
            'user_id' => $userId,
            'form_id' => $formId,
            'review_status' => $reviewStatus,
            'is_active' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $id;
    }
}

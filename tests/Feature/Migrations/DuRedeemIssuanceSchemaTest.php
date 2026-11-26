<?php

namespace Tests\Feature\Migrations;

use App\Enums\DuRedeemStatus;
use App\Models\DuRedeemIssuance;
use App\Models\FormAnswer;
use App\Models\FormReviewEmailOutbox;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

class DuRedeemIssuanceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuance_schema_has_nullable_safe_fields_and_unique_answer_relation(): void
    {
        $this->assertTrue(Schema::hasTable('du_redeem_issuances'));
        $this->assertTrue(Schema::hasColumn('du_redeem_issuances', 'form_answer_id'));
        $this->assertTrue(Schema::hasColumn('du_redeem_issuances', 'course_uid'));
        $this->assertTrue(Schema::hasColumn('du_redeem_issuances', 'redeem_code'));
        $this->assertTrue(Schema::hasIndex('du_redeem_issuances', 'du_redeem_issuances_form_answer_id_unique'));

        $columns = collect(Schema::getColumns('du_redeem_issuances'))->keyBy('name');
        foreach (['course_uid', 'redeem_code', 'failure_reason', 'attempted_at', 'issued_at'] as $column) {
            $this->assertTrue($columns[$column]['nullable']);
        }
        $statusDefault = trim((string) $columns['status']['default'], "'\"");
        $attemptCountDefault = trim((string) $columns['attempt_count']['default'], "'\"");
        $this->assertSame('pending', strtolower($statusDefault));
        $this->assertSame(0, (int) $attemptCountDefault);

        $answer = FormAnswer::factory()->create();
        $this->assertInstanceOf(HasOne::class, $answer->duRedeemIssuance());
        $issuance = DuRedeemIssuance::create(['form_answer_id' => $answer->id]);
        $this->assertSame($issuance->id, $answer->duRedeemIssuance()->first()->id);
        $this->assertSame(DuRedeemStatus::Pending->value, $issuance->fresh()->status->value);
    }

    public function test_response_columns_are_nullable_and_catalog_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('du_courses'));
        foreach ([
            'du_uid', 'course_title', 'target_email', 'max_uses', 'used_count',
            'is_active', 'expires_at', 'du_created_at', 'response_payload',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('du_redeem_issuances', $column), $column);
            $this->assertTrue(collect(Schema::getColumns('du_redeem_issuances'))->keyBy('name')[$column]['nullable'], $column);
        }
    }

    public function test_additive_response_columns_migration_rolls_back_without_touching_issuance_rows(): void
    {
        $answer = FormAnswer::factory()->create();
        $issuance = DuRedeemIssuance::create([
            'form_answer_id' => $answer->id,
            'status' => DuRedeemStatus::Issued,
            'redeem_code' => 'DU-OLD',
        ]);
        $columns = [
            'du_uid', 'course_title', 'target_email', 'max_uses', 'used_count',
            'is_active', 'expires_at', 'du_created_at', 'response_payload',
        ];
        $migration = require base_path('database/migrations/2026_11_24_000002_add_du_response_columns_to_du_redeem_issuances_table.php');

        $migration->down();
        foreach ($columns as $column) {
            $this->assertFalse(Schema::hasColumn('du_redeem_issuances', $column), $column);
        }
        $this->assertDatabaseHas('du_redeem_issuances', ['id' => $issuance->id, 'redeem_code' => 'DU-OLD']);

        $migration->up();
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('du_redeem_issuances', $column), $column);
        }
        $restored = $issuance->fresh();
        $this->assertSame('DU-OLD', $restored?->redeem_code);
        $this->assertSame(DuRedeemStatus::Issued, $restored?->status);
    }

    public function test_du_issued_outbox_action_rollback_deletes_du_issued_rows_and_restores_the_enum(): void
    {
        $answer = FormAnswer::factory()->create();
        $accepted = $this->outboxRow($answer->id, FormReviewEmailOutbox::ACTION_ACCEPTED, 'rollback-accepted');
        $duIssued = $this->outboxRow($answer->id, FormReviewEmailOutbox::ACTION_DU_ISSUED, 'rollback-du-issued');
        $migration = require base_path('database/migrations/2026_11_23_000001_add_du_issued_action_to_form_review_email_outboxes.php');

        $migration->down();

        $this->assertDatabaseMissing('form_review_email_outboxes', ['id' => $duIssued->id]);
        $this->assertDatabaseHas('form_review_email_outboxes', [
            'id' => $accepted->id,
            'action' => FormReviewEmailOutbox::ACTION_ACCEPTED,
        ]);

        try {
            $this->outboxRow($answer->id, FormReviewEmailOutbox::ACTION_DU_ISSUED, 'rollback-after-down');
            $this->fail('Enum yang sudah menyempit harus menolak baris du_issued.');
        } catch (QueryException) {
            // Enum kembali ke accepted/rejected.
        }

        $migration->up();

        $this->outboxRow($answer->id, FormReviewEmailOutbox::ACTION_DU_ISSUED, 'rollback-after-up');
        $this->assertDatabaseHas('form_review_email_outboxes', [
            'delivery_key' => 'rollback-after-up',
            'action' => FormReviewEmailOutbox::ACTION_DU_ISSUED,
        ]);
    }

    private function outboxRow(string $answerId, string $action, string $deliveryKey): FormReviewEmailOutbox
    {
        return FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answerId,
            'action' => $action,
            'delivery_key' => $deliveryKey,
            'status' => FormReviewEmailOutbox::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => now(),
        ]);
    }

    public function test_deleting_form_answer_cascades_to_issuance(): void
    {
        $answer = FormAnswer::factory()->create();
        DuRedeemIssuance::create(['form_answer_id' => $answer->id]);
        $this->assertDatabaseHas('form_answers', ['id' => $answer->id]);
        $answer->delete();
        $this->assertDatabaseMissing('du_redeem_issuances', ['form_answer_id' => $answer->id]);
    }

    public function test_migration_rollback_drops_only_issuance_table_and_preserves_form_answer(): void
    {
        $answer = FormAnswer::factory()->create();
        $migration = require base_path('database/migrations/2026_11_22_000001_create_du_redeem_issuances_table.php');

        try {
            $migration->down();
            $this->assertFalse(Schema::hasTable('du_redeem_issuances'));
            $this->assertDatabaseHas('form_answers', ['id' => $answer->id]);
            $migration->up();
            $this->assertTrue(Schema::hasTable('du_redeem_issuances'));
            (require base_path('database/migrations/2026_11_24_000002_add_du_response_columns_to_du_redeem_issuances_table.php'))->up();
            $this->assertTrue(Schema::hasColumn('du_redeem_issuances', 'du_uid'));
            $this->assertTrue(Schema::hasColumn('du_redeem_issuances', 'response_payload'));
            $this->assertDatabaseHas('form_answers', ['id' => $answer->id]);
        } catch (Throwable $exception) {
            $this->fail('Issuance migration rollback round-trip failed: '.$exception->getMessage());
        }
    }
}

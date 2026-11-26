<?php

namespace Tests\Feature\Forms;

use App\Enums\DuRedeemStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Models\DuCourse;
use App\Models\DuRedeemIssuance;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormReviewEmailOutbox;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DuRedeemFixture;
use Tests\TestCase;

final class DuRedeemReissueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Event $event;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Queue::fake();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->event = Event::factory()->create(['created_by' => $this->admin->id]);
        $this->form = Form::factory()->create([
            'event_id' => $this->event->id,
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single'],
        ]);
        DuCourse::factory()->create(['uid' => 'course-test', 'title' => 'Test', 'is_active' => true]);
    }

    public function test_resend_creates_one_new_outbox_with_a_unique_key_and_returns_the_stored_code(): void
    {
        Http::fake();
        [$answer] = $this->fixture(DuRedeemStatus::Issued);

        $this->actingAs($this->admin)
            ->postJson($this->issuePath($answer), ['action' => 'resend'])
            ->assertOk()
            ->assertJson([
                'status' => 'issued',
                'code' => 'OLD-CODE',
                'message' => 'Email dikirim ulang.',
                'created_new_code' => false,
            ]);

        Http::assertNothingSent();

        $outboxes = FormReviewEmailOutbox::query()->where('form_answer_id', $answer->id)->get();
        $this->assertCount(2, $outboxes);
        $resend = $outboxes->firstWhere('delivery_key', '!=', 'form-review-'.$answer->id.'-du-issued');
        $this->assertNotNull($resend);
        $this->assertStringStartsWith('du-issued-resend-', (string) $resend->delivery_key);
        $this->assertSame(FormReviewEmailOutbox::ACTION_DU_ISSUED, $resend->action);
    }

    public function test_reissue_success_stores_the_new_code_and_creates_a_new_outbox_key(): void
    {
        $this->fakeRedeem(201, 'NEW-CODE');
        [$answer] = $this->fixture(DuRedeemStatus::Issued);

        $this->actingAs($this->admin)
            ->postJson($this->issuePath($answer), ['action' => 'reissue'])
            ->assertOk()
            ->assertJson([
                'status' => 'issued',
                'code' => 'NEW-CODE',
                'message' => null,
                'created_new_code' => true,
            ]);

        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => 'issued',
            'redeem_code' => 'NEW-CODE',
            'du_uid' => 'redeem-NEW-CODE',
            'attempt_count' => 2,
        ]);

        $issuance = DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->firstOrFail();
        $this->assertSame('NEW-CODE', $issuance->response_payload['code'] ?? null);

        $this->assertDatabaseHas('form_review_email_outboxes', [
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_DU_ISSUED,
            'delivery_key' => 'form-review-'.$answer->id.'-du-issued-r2',
        ]);
        Http::assertSentCount(1);
    }

    #[DataProvider('failedReissueResponses')]
    public function test_failed_reissue_restores_the_old_issuance_and_creates_no_outbox(int $duStatus): void
    {
        $this->fakeRedeem($duStatus, 'NEW-CODE');
        [$answer] = $this->fixture(DuRedeemStatus::Issued);
        $oldIssuedAt = DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->firstOrFail()->issued_at;

        $this->actingAs($this->admin)
            ->postJson($this->issuePath($answer), ['action' => 'reissue'])
            ->assertOk()
            ->assertJson([
                'status' => 'issued',
                'code' => 'OLD-CODE',
                'message' => 'Penerbitan ulang gagal; kode lama tetap berlaku.',
                'created_new_code' => false,
            ]);

        $issuance = DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->firstOrFail();
        $this->assertSame(DuRedeemStatus::Issued, $issuance->status);
        $this->assertSame('OLD-CODE', $issuance->redeem_code);
        $this->assertSame('old-uid', $issuance->du_uid);
        $this->assertSame(['uid' => 'old-uid', 'code' => 'OLD-CODE'], $issuance->response_payload);
        $this->assertSame(2, $issuance->attempt_count);
        $this->assertTrue($issuance->issued_at->equalTo($oldIssuedAt));

        $this->assertSame(1, FormReviewEmailOutbox::query()
            ->where('form_answer_id', $answer->id)
            ->where('action', FormReviewEmailOutbox::ACTION_DU_ISSUED)
            ->count());
    }

    /** @return array<string, array{0: int}> */
    public static function failedReissueResponses(): array
    {
        return [
            'rejected' => [422],
            'ambiguous' => [500],
        ];
    }

    public function test_reissue_of_an_unknown_row_makes_no_du_call(): void
    {
        Http::fake();
        [$answer] = $this->fixture(DuRedeemStatus::Unknown);

        $this->actingAs($this->admin)
            ->postJson($this->issuePath($answer), ['action' => 'reissue'])
            ->assertOk()
            ->assertJson([
                'status' => 'unknown',
                'code' => null,
                'message' => 'Status DU belum pasti; rekonsiliasi manual.',
                'created_new_code' => false,
            ]);

        Http::assertNothingSent();
        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => 'unknown',
            'attempt_count' => 1,
        ]);
    }

    public function test_normal_issue_replays_while_reissue_requires_the_explicit_action(): void
    {
        $this->fakeRedeem(201, 'NEW-CODE');
        [$answer] = $this->fixture(DuRedeemStatus::Issued);

        $this->actingAs($this->admin)
            ->postJson($this->issuePath($answer), [])
            ->assertOk()
            ->assertJson([
                'status' => 'issued',
                'code' => 'OLD-CODE',
                'created_new_code' => false,
            ]);

        Http::assertNothingSent();

        $this->actingAs($this->admin)
            ->postJson($this->issuePath($answer), ['action' => 'reissue'])
            ->assertOk()
            ->assertJson([
                'status' => 'issued',
                'code' => 'NEW-CODE',
                'created_new_code' => true,
            ]);
    }

    public function test_invalid_action_is_rejected(): void
    {
        Http::fake();
        [$answer] = $this->fixture(DuRedeemStatus::Issued);

        $this->actingAs($this->admin)
            ->postJson($this->issuePath($answer), ['action' => 'delete'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('action');

        Http::assertNothingSent();
    }

    /** @return array{0: FormAnswer} */
    private function fixture(DuRedeemStatus $status): array
    {
        $answer = FormAnswer::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => User::factory()->create(['email' => 'reissue@example.test'])->id,
            'answers' => ['du_course_uid' => 'course-test'],
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        $issued = $status === DuRedeemStatus::Issued;

        DuRedeemIssuance::query()->create([
            'form_answer_id' => $answer->id,
            'course_uid' => 'course-test',
            'status' => $status,
            'redeem_code' => $issued ? 'OLD-CODE' : null,
            'failure_reason' => null,
            'attempt_count' => 1,
            'du_uid' => $issued ? 'old-uid' : null,
            'course_title' => $issued ? 'Old Title' : null,
            'target_email' => $issued ? 'old@example.test' : null,
            'max_uses' => $issued ? 1 : null,
            'used_count' => $issued ? 0 : null,
            'is_active' => $issued,
            'response_payload' => $issued ? ['uid' => 'old-uid', 'code' => 'OLD-CODE'] : null,
            'issued_at' => $issued ? Carbon::parse('2026-01-01T00:00:00Z') : null,
            'expires_at' => $issued ? Carbon::parse('2027-01-01T00:00:00Z') : null,
        ]);

        FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_DU_ISSUED,
            'delivery_key' => 'form-review-'.$answer->id.'-du-issued',
            'status' => FormReviewEmailOutbox::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => now(),
        ]);

        return [$answer];
    }

    private function fakeRedeem(int $status, string $code): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => DuRedeemFixture::success($code),
        ], $status)]);
    }

    private function issuePath(FormAnswer $answer): string
    {
        return route('dashboard.events.forms.submissions.du-issuance', [
            'event' => $this->event,
            'form' => $this->form,
            'formAnswer' => $answer,
        ]);
    }
}

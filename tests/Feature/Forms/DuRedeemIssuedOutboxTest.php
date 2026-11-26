<?php

namespace Tests\Feature\Forms;

use App\Enums\FormAnswerReviewStatus;
use App\Jobs\DispatchFormReviewEmailOutboxJob;
use App\Jobs\SendDuRedeemIssuedJob;
use App\Models\DuCourse;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormReviewEmailOutbox;
use App\Models\User;
use App\Services\Form\FormAnswerReviewService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DuRedeemFixture;
use Tests\TestCase;

final class DuRedeemIssuedOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_du_issued_outbox_uses_distinct_action_and_stable_delivery_key(): void
    {
        Queue::fake();
        $this->fakeSuccessfulDuIssuance();
        [$event, $form, $answer] = $this->dKelasAnswer();

        app(FormAnswerReviewService::class)->reviewOne(
            $event,
            $form,
            $answer,
            FormAnswerReviewStatus::Accepted,
            User::factory()->create(),
        );

        $duOutbox = FormReviewEmailOutbox::query()
            ->where('form_answer_id', $answer->id)
            ->where('action', FormReviewEmailOutbox::ACTION_DU_ISSUED)
            ->firstOrFail();

        $acceptedOutbox = FormReviewEmailOutbox::query()
            ->where('form_answer_id', $answer->id)
            ->where('action', FormReviewEmailOutbox::ACTION_ACCEPTED)
            ->firstOrFail();

        $this->assertSame('form-review-'.$answer->id.'-du-issued', $duOutbox->delivery_key);
        $this->assertNotSame($acceptedOutbox->delivery_key, $duOutbox->delivery_key);
        $this->assertSame(1, FormReviewEmailOutbox::query()
            ->where('form_answer_id', $answer->id)
            ->where('action', FormReviewEmailOutbox::ACTION_ACCEPTED)
            ->count());
    }

    public function test_dispatch_routes_du_issued_action_to_distinct_job(): void
    {
        Queue::fake();
        [$answer, $outbox] = $this->pendingDuIssuedOutbox();

        (new DispatchFormReviewEmailOutboxJob((string) $outbox->id))->handle();

        Queue::assertPushed(SendDuRedeemIssuedJob::class, function (SendDuRedeemIssuedJob $job) use ($answer, $outbox): bool {
            return $job->formAnswerId === (string) $answer->id
                && $job->deliveryKey === $outbox->delivery_key;
        });
        $this->assertSame(FormReviewEmailOutbox::STATUS_QUEUED, $outbox->refresh()->status);
    }

    public function test_duplicate_dispatch_does_not_enqueue_duplicate_du_issued_job(): void
    {
        Queue::fake();
        [, $outbox] = $this->pendingDuIssuedOutbox();
        $dispatch = new DispatchFormReviewEmailOutboxJob((string) $outbox->id);

        $dispatch->handle();
        $dispatch->handle();

        Queue::assertPushed(SendDuRedeemIssuedJob::class, 1);
        $this->assertSame(FormReviewEmailOutbox::STATUS_QUEUED, $outbox->refresh()->status);
    }

    public function test_non_du_accepted_review_creates_no_du_issued_outbox(): void
    {
        Queue::fake();
        Http::fake();
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => User::factory()->create(['email' => 'non-du-outbox@example.test'])->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        app(FormAnswerReviewService::class)->reviewOne(
            $event,
            $form,
            $answer,
            FormAnswerReviewStatus::Accepted,
            User::factory()->create(),
        );

        $this->assertSame(1, FormReviewEmailOutbox::query()
            ->where('form_answer_id', $answer->id)
            ->where('action', FormReviewEmailOutbox::ACTION_ACCEPTED)
            ->count());
        $this->assertSame(0, $this->duIssuedOutboxCount($answer));
        Http::assertNothingSent();
    }

    public function test_failed_du_issuance_creates_no_du_issued_outbox(): void
    {
        Queue::fake();
        $this->fakeDuRedeemResponse(422);
        [$event, $form, $answer] = $this->dKelasAnswer();

        app(FormAnswerReviewService::class)->reviewOne(
            $event,
            $form,
            $answer,
            FormAnswerReviewStatus::Accepted,
            User::factory()->create(),
        );

        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => 'failed',
        ]);
        $this->assertSame(0, $this->duIssuedOutboxCount($answer));
    }

    public function test_unknown_du_issuance_creates_no_du_issued_outbox(): void
    {
        Queue::fake();
        $this->fakeDuRedeemResponse(500);
        [$event, $form, $answer] = $this->dKelasAnswer();

        app(FormAnswerReviewService::class)->reviewOne(
            $event,
            $form,
            $answer,
            FormAnswerReviewStatus::Accepted,
            User::factory()->create(),
        );

        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => 'unknown',
        ]);
        $this->assertSame(0, $this->duIssuedOutboxCount($answer));
    }

    public function test_manual_issuance_endpoint_creates_single_du_issued_outbox_row(): void
    {
        Queue::fake();
        $this->fakeSuccessfulDuIssuance();
        [$admin, $event, $form, $answer] = $this->dKelasManualIssuanceFixture();
        $url = route('dashboard.events.forms.submissions.du-issuance', [
            'event' => $event,
            'form' => $form,
            'formAnswer' => $answer,
        ]);

        $this->actingAs($admin)->postJson($url, [])->assertOk()->assertJson(['status' => 'issued']);

        $this->assertSame(1, $this->duIssuedOutboxCount($answer));
        $this->assertDatabaseHas('form_review_email_outboxes', [
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_DU_ISSUED,
            'delivery_key' => 'form-review-'.$answer->id.'-du-issued',
        ]);

        $this->actingAs($admin)->postJson($url, [])->assertOk();

        $this->assertSame(1, $this->duIssuedOutboxCount($answer));
    }

    public function test_review_accept_then_manual_retry_keeps_single_du_issued_outbox_row(): void
    {
        Queue::fake();
        $this->fakeSuccessfulDuIssuance();
        [$admin, $event, $form, $answer] = $this->dKelasManualIssuanceFixture(FormAnswerReviewStatus::Pending);

        app(FormAnswerReviewService::class)->reviewOne(
            $event,
            $form,
            $answer,
            FormAnswerReviewStatus::Accepted,
            $admin,
        );

        $this->assertSame(1, $this->duIssuedOutboxCount($answer));

        $this->actingAs($admin)->postJson(route('dashboard.events.forms.submissions.du-issuance', [
            'event' => $event,
            'form' => $form,
            'formAnswer' => $answer,
        ]), [])->assertOk();

        $this->assertSame(1, $this->duIssuedOutboxCount($answer));
    }

    public function test_manual_retry_recreates_a_lost_du_issued_outbox_row_from_the_stored_code(): void
    {
        Queue::fake();
        $this->fakeSuccessfulDuIssuance();
        [$admin, $event, $form, $answer] = $this->dKelasManualIssuanceFixture();
        $url = route('dashboard.events.forms.submissions.du-issuance', [
            'event' => $event,
            'form' => $form,
            'formAnswer' => $answer,
        ]);

        $this->actingAs($admin)->postJson($url, [])->assertOk()->assertJson(['status' => 'issued']);
        $this->assertSame(1, $this->duIssuedOutboxCount($answer));

        // Simulate a lost outbox write; the retry must rebuild the intent from the stored code.
        FormReviewEmailOutbox::query()
            ->where('form_answer_id', $answer->id)
            ->where('action', FormReviewEmailOutbox::ACTION_DU_ISSUED)
            ->delete();

        $this->actingAs($admin)
            ->postJson($url, [])
            ->assertOk()
            ->assertJson(['status' => 'issued', 'code' => 'safe-test-code']);

        $this->assertSame(1, $this->duIssuedOutboxCount($answer));
        Http::assertSentCount(1);
    }

    /** @return array{0: Event, 1: Form, 2: FormAnswer} */
    private function dKelasAnswer(): array
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single'],
        ]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => User::factory()->create(['email' => 'du-outbox@example.test'])->id,
            'answers' => ['du_course_uid' => 'course-test'],
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        return [$event, $form, $answer];
    }

    /** @return array{0: FormAnswer, 1: FormReviewEmailOutbox} */
    private function pendingDuIssuedOutbox(): array
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single'],
        ]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_DU_ISSUED,
            'delivery_key' => 'form-review-'.$answer->id.'-du-issued',
            'status' => FormReviewEmailOutbox::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => now(),
        ]);

        return [$answer, $outbox];
    }

    /** @return array{0: User, 1: Event, 2: Form, 3: FormAnswer} */
    private function dKelasManualIssuanceFixture(FormAnswerReviewStatus $reviewStatus = FormAnswerReviewStatus::Accepted): array
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single'],
        ]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => User::factory()->create(['email' => 'du-manual-issue@example.test'])->id,
            'answers' => ['du_course_uid' => 'course-test'],
            'review_status' => $reviewStatus,
        ]);

        return [$admin, $event, $form, $answer];
    }

    private function duIssuedOutboxCount(FormAnswer $answer): int
    {
        return FormReviewEmailOutbox::query()
            ->where('form_answer_id', $answer->id)
            ->where('action', FormReviewEmailOutbox::ACTION_DU_ISSUED)
            ->count();
    }

    private function fakeSuccessfulDuIssuance(): void
    {
        $this->fakeDuRedeemResponse(201);
    }

    private function fakeDuRedeemResponse(int $redeemStatus): void
    {
        DuCourse::factory()->create(['uid' => 'course-test', 'title' => 'Test', 'is_active' => true]);

        Http::fake(fn () => Http::response([
            'success' => true,
            'data' => DuRedeemFixture::success('safe-test-code'),
        ], $redeemStatus));
    }
}

<?php

namespace Tests\Feature\Forms;

use App\Enums\FormAnswerReviewStatus;
use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Jobs\DispatchFormReviewEmailOutboxJob;
use App\Jobs\SendRegistrationAcceptedJob;
use App\Jobs\SendRegistrationRejectedJob;
use App\Exceptions\RedactedMailDeliveryException;
use App\Mail\RegistrationRejectedMail;
use App\Models\Event;
use App\Models\EmailLog;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormReviewEmailOutbox;
use App\Models\User;
use App\Services\Form\FormAnswerReviewService;
use App\Services\Form\FormReviewEmailOutboxRecovery;
use App\Services\Registration\FormAnswerRecipientResolver;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BulkFormAnswerReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_mode_accept_creates_one_durable_outbox_intent_per_submission(): void
    {
        Queue::fake();
        $actor = User::factory()->create();
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $answers = FormAnswer::factory()->count(2)->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $result = app(FormAnswerReviewService::class)->reviewMany(
            $event,
            $form,
            new Collection($answers->modelKeys()),
            FormAnswerReviewStatus::Accepted,
            $actor,
        );

        $this->assertSame(2, $result->processedCount());
        $this->assertSame(2, FormAnswer::query()->whereKey($answers->modelKeys())->where('review_status', 'accepted')->count());
        $this->assertSame(2, FormReviewEmailOutbox::query()->count());
        $this->assertSame(2, FormReviewEmailOutbox::query()->where('status', 'pending')->count());
    }

    public function test_mixed_team_selection_aborts_without_mutating_single_submission(): void
    {
        $actor = User::factory()->create();
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $single = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);
        $teamMember = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $this->expectException(HttpException::class);

        try {
            app(FormAnswerReviewService::class)->reviewMany(
                $event,
                $form,
                new Collection([$single->id, $teamMember->id]),
                FormAnswerReviewStatus::Accepted,
                $actor,
            );
        } finally {
            $this->assertSame('pending', $single->refresh()->review_status->value);
            $this->assertSame('pending', $teamMember->refresh()->review_status->value);
            $this->assertSame(0, FormReviewEmailOutbox::query()->count());
        }
    }

    public function test_single_mode_reject_updates_counter_and_queues_rejection_intents(): void
    {
        Queue::fake();
        $actor = User::factory()->create();
        $event = Event::factory()->create(['registered_count' => 2]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $answers = FormAnswer::factory()->count(2)->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $result = app(FormAnswerReviewService::class)->reviewMany(
            $event,
            $form,
            new Collection($answers->modelKeys()),
            FormAnswerReviewStatus::Rejected,
            $actor,
        );

        $this->assertSame(2, $result->processedCount());
        $this->assertSame(0, (int) $event->refresh()->registered_count);
        $this->assertSame(2, FormReviewEmailOutbox::query()->where('action', 'rejected')->count());
    }

    public function test_stale_selection_rolls_back_all_rows_and_outbox_intents(): void
    {
        $actor = User::factory()->create();
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $pending = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);
        $stale = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        $this->expectException(HttpException::class);

        try {
            app(FormAnswerReviewService::class)->reviewMany(
                $event,
                $form,
                new Collection([$pending->id, $stale->id]),
                FormAnswerReviewStatus::Rejected,
                $actor,
            );
        } finally {
            $this->assertSame('pending', $pending->refresh()->review_status->value);
            $this->assertSame('accepted', $stale->refresh()->review_status->value);
            $this->assertSame(0, FormReviewEmailOutbox::query()->count());
        }
    }

    public function test_outbox_dispatch_marks_intent_queued_and_reuses_stable_delivery_key(): void
    {
        Queue::fake();
        $actor = User::factory()->create();
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        app(FormAnswerReviewService::class)->reviewMany(
            $event,
            $form,
            new Collection([$answer->id]),
            FormAnswerReviewStatus::Accepted,
            $actor,
        );

        $outbox = FormReviewEmailOutbox::query()->firstOrFail();
        (new DispatchFormReviewEmailOutboxJob((string) $outbox->id))->handle();

        $this->assertSame('queued', $outbox->refresh()->status);
        Queue::assertPushed(SendRegistrationAcceptedJob::class, function (SendRegistrationAcceptedJob $job) use ($answer, $outbox): bool {
            return $job->formAnswerId === (string) $answer->id
                && $job->deliveryKey === $outbox->delivery_key;
        });
    }

    public function test_queue_dispatch_failure_leaves_committed_intent_pending_for_recovery(): void
    {
        Queue::fake();
        $actor = User::factory()->create();
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);
        $originalDispatcher = app(BusDispatcher::class);
        $failingDispatcher = \Mockery::mock(BusDispatcher::class);
        $failingDispatcher->shouldReceive('dispatch')->andThrow(new \RuntimeException('queue unavailable'));
        $this->app->instance(BusDispatcher::class, $failingDispatcher);

        try {
            app(FormAnswerReviewService::class)->reviewMany(
                $event,
                $form,
                new Collection([$answer->id]),
                FormAnswerReviewStatus::Accepted,
                $actor,
            );
        } finally {
            $this->app->instance(BusDispatcher::class, $originalDispatcher);
        }

        $this->assertDatabaseHas('form_review_email_outboxes', [
            'form_answer_id' => $answer->id,
            'status' => FormReviewEmailOutbox::STATUS_PENDING,
        ]);
        Queue::assertNothingPushed();
    }

    public function test_after_commit_dispatch_failure_does_not_revert_an_advanced_outbox(): void
    {
        Queue::fake();
        $actor = User::factory()->create();
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);
        $originalDispatcher = app(BusDispatcher::class);
        $advancedDispatcher = \Mockery::mock(BusDispatcher::class);
        $advancedDispatcher->shouldReceive('dispatch')->once()->andReturnUsing(function (object $job): never {
            $outbox = FormReviewEmailOutbox::query()->firstOrFail();
            $outbox->forceFill([
                'status' => FormReviewEmailOutbox::STATUS_DISPATCHING,
                'attempts' => 1,
                'queued_at' => now(),
            ])->save();

            throw new \RuntimeException('queue failed after state advancement');
        });
        $this->app->instance(BusDispatcher::class, $advancedDispatcher);

        try {
            app(FormAnswerReviewService::class)->reviewMany(
                $event,
                $form,
                new Collection([$answer->id]),
                FormAnswerReviewStatus::Accepted,
                $actor,
            );
        } finally {
            $this->app->instance(BusDispatcher::class, $originalDispatcher);
        }

        $outbox = FormReviewEmailOutbox::query()->firstOrFail();
        $this->assertSame(FormReviewEmailOutbox::STATUS_DISPATCHING, $outbox->status);
        $this->assertSame(1, (int) $outbox->attempts);
        Queue::assertNothingPushed();
    }

    public function test_transport_failure_then_retry_marks_failed_intent_sent(): void
    {
        $user = User::factory()->create(['email' => 'retry@example.com']);
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $deliveryKey = 'form-review-'.$answer->id.'-rejected';
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => $deliveryKey,
            'status' => FormReviewEmailOutbox::STATUS_QUEUED,
            'available_at' => now(),
        ]);

        Mail::shouldReceive('to->send')->once()->andThrow(new \RuntimeException('temporary SMTP failure'));
        try {
            (new SendRegistrationRejectedJob((string) $answer->id, $deliveryKey))
                ->handle(app(FormAnswerRecipientResolver::class));
        } catch (RedactedMailDeliveryException) {
            // The queue worker retries the same serialized delivery key.
        }

        $this->assertSame(FormReviewEmailOutbox::STATUS_FAILED, $outbox->refresh()->status);

        Mail::shouldReceive('to->send')->once()->andReturn(null);
        (new SendRegistrationRejectedJob((string) $answer->id, $deliveryKey)
        )->handle(app(FormAnswerRecipientResolver::class));

        $this->assertSame(FormReviewEmailOutbox::STATUS_SENT, $outbox->refresh()->status);
    }

    public function test_pending_recovery_dispatches_each_intent_once_with_durable_identity(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $deliveryKey = 'form-review-'.$answer->id.'-rejected';
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => $deliveryKey,
            'status' => FormReviewEmailOutbox::STATUS_PENDING,
            'available_at' => now()->subMinute(),
        ]);

        $recovery = app(FormReviewEmailOutboxRecovery::class);
        $this->assertSame(1, $recovery->dispatchPending());
        $this->assertSame(0, $recovery->dispatchPending());
        $this->assertSame(FormReviewEmailOutbox::STATUS_DISPATCHING, $outbox->refresh()->status);
        Queue::assertPushed(DispatchFormReviewEmailOutboxJob::class, 1);

        $queued = null;
        Queue::assertPushed(DispatchFormReviewEmailOutboxJob::class, function (DispatchFormReviewEmailOutboxJob $job) use (&$queued, $outbox): bool {
            $queued = $job;

            return $job->outboxId === (string) $outbox->id;
        });
        self::assertInstanceOf(DispatchFormReviewEmailOutboxJob::class, $queued);
        $queued->handle();
        $this->assertSame(FormReviewEmailOutbox::STATUS_QUEUED, $outbox->refresh()->status);
        Queue::assertPushed(SendRegistrationRejectedJob::class, function (SendRegistrationRejectedJob $job) use ($answer, $deliveryKey): bool {
            return $job->formAnswerId === (string) $answer->id
                && $job->deliveryKey === $deliveryKey;
        });
    }

    public function test_stale_dispatcher_lease_recovery_releases_the_lease_and_dispatches_once(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $deliveryKey = 'form-review-'.$answer->id.'-rejected';
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => $deliveryKey,
            'status' => FormReviewEmailOutbox::STATUS_DISPATCHING,
            'attempts' => 1,
            'available_at' => now()->subMinutes(20),
            'queued_at' => now()->subMinutes(20),
        ]);

        $recovery = app(FormReviewEmailOutboxRecovery::class);
        $this->assertSame(1, $recovery->dispatchPending());
        $this->assertSame(0, $recovery->dispatchPending());
        $this->assertSame(FormReviewEmailOutbox::STATUS_DISPATCHING, $outbox->refresh()->status);
        $this->assertSame(2, (int) $outbox->attempts);
        Queue::assertPushed(DispatchFormReviewEmailOutboxJob::class, 1);

        $queued = null;
        Queue::assertPushed(DispatchFormReviewEmailOutboxJob::class, function (DispatchFormReviewEmailOutboxJob $job) use (&$queued, $outbox): bool {
            $queued = $job;

            return $job->outboxId === (string) $outbox->id && $job->claimed;
        });
        self::assertInstanceOf(DispatchFormReviewEmailOutboxJob::class, $queued);
        $queued->handle();
        $this->assertSame(FormReviewEmailOutbox::STATUS_QUEUED, $outbox->refresh()->status);
        Queue::assertPushed(SendRegistrationRejectedJob::class, function (SendRegistrationRejectedJob $job) use ($answer, $deliveryKey): bool {
            return $job->formAnswerId === (string) $answer->id
                && $job->deliveryKey === $deliveryKey;
        });
    }

    public function test_replaying_claimed_dispatcher_does_not_enqueue_a_second_recipient_job(): void
    {
        Queue::fake();
        Mail::fake();
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $deliveryKey = 'form-review-'.$answer->id.'-rejected';
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => $deliveryKey,
            'status' => FormReviewEmailOutbox::STATUS_DISPATCHING,
            'attempts' => 1,
            'queued_at' => now(),
        ]);
        $dispatcher = new DispatchFormReviewEmailOutboxJob((string) $outbox->id, true, 1);

        $dispatcher->handle();
        $dispatcher->handle();

        Queue::assertPushed(SendRegistrationRejectedJob::class, 1);
        $recipient = null;
        Queue::assertPushed(SendRegistrationRejectedJob::class, function (SendRegistrationRejectedJob $job) use (&$recipient): bool {
            $recipient = $job;

            return true;
        });
        self::assertInstanceOf(SendRegistrationRejectedJob::class, $recipient);
        $recipient->handle(app(FormAnswerRecipientResolver::class));
        $recipient->handle(app(FormAnswerRecipientResolver::class));
        Mail::assertSent(RegistrationRejectedMail::class, 1);
    }

    public function test_recipient_enqueue_crash_fails_closed_without_a_second_enqueue_or_delivery(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'ambiguous-review@example.com']);
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => 'form-review-'.$answer->id.'-rejected',
            'status' => FormReviewEmailOutbox::STATUS_PENDING,
        ]);
        $captured = null;
        $dispatcher = \Mockery::mock(BusDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->andReturnUsing(function (object $job) use (&$captured): never {
            $captured = $job;
            throw new \RuntimeException('queue acknowledged then process crashed');
        });
        $originalDispatcher = app(BusDispatcher::class);
        $this->app->instance(BusDispatcher::class, $dispatcher);

        try {
            (new DispatchFormReviewEmailOutboxJob((string) $outbox->id))->handle();
            (new DispatchFormReviewEmailOutboxJob((string) $outbox->id))->handle();
        } finally {
            $this->app->instance(BusDispatcher::class, $originalDispatcher);
        }

        $this->assertSame(FormReviewEmailOutbox::STATUS_MANUAL_REVIEW, $outbox->refresh()->status);
        self::assertInstanceOf(SendRegistrationRejectedJob::class, $captured);
        $captured->handle(app(FormAnswerRecipientResolver::class));
        Mail::assertNothingSent();
    }

    public function test_dispatcher_sync_path_delivers_and_marks_the_intent_sent(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'sync-review@example.com']);
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => 'form-review-'.$answer->id.'-rejected',
            'status' => FormReviewEmailOutbox::STATUS_PENDING,
        ]);

        (new DispatchFormReviewEmailOutboxJob((string) $outbox->id))->handle();

        Mail::assertSent(RegistrationRejectedMail::class, 1);
        $this->assertSame(FormReviewEmailOutbox::STATUS_SENT, $outbox->refresh()->status);
    }

    public function test_recovery_does_not_reclaim_a_delayed_or_in_flight_queued_recipient(): void
    {
        Queue::fake();
        Mail::fake();
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => 'form-review-'.$answer->id.'-rejected',
            'status' => FormReviewEmailOutbox::STATUS_QUEUED,
            'queued_at' => now()->subMinutes(20),
            'available_at' => now()->subMinutes(20),
        ]);
        $inFlightAnswer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $inFlightKey = 'form-review-'.$inFlightAnswer->id.'-rejected';
        $inFlightOutbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $inFlightAnswer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => $inFlightKey,
            'status' => FormReviewEmailOutbox::STATUS_QUEUED,
            'queued_at' => now(),
        ]);
        self::assertTrue(FormReviewEmailOutbox::claimDelivery($inFlightKey));

        $this->assertSame(0, app(FormReviewEmailOutboxRecovery::class)->dispatchPending());
        $this->assertSame(FormReviewEmailOutbox::STATUS_QUEUED, $outbox->refresh()->status);
        $this->assertSame(FormReviewEmailOutbox::STATUS_SENDING, $inFlightOutbox->refresh()->status);
        Queue::assertNothingPushed();

        (new SendRegistrationRejectedJob((string) $answer->id, $outbox->delivery_key))
            ->handle(app(FormAnswerRecipientResolver::class));
        Mail::assertSent(RegistrationRejectedMail::class, 1);
    }

    public function test_stale_sending_with_sent_log_is_reconciled_without_dispatch(): void
    {
        Queue::fake();
        $user = User::factory()->create(['email' => 'reconciled-review@example.com']);
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $deliveryKey = 'form-review-'.$answer->id.'-rejected';
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => $deliveryKey,
            'status' => FormReviewEmailOutbox::STATUS_SENDING,
            'attempts' => 1,
            'queued_at' => now()->subMinutes(20),
        ]);
        EmailLog::query()->create([
            'form_answer_id' => $answer->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'recipient_email' => $user->email,
            'notification_type' => EmailNotificationType::RegistrationRejected,
            'delivery_key' => $deliveryKey,
            'status' => EmailLogStatus::Sent,
            'sent_at' => now()->subMinutes(19),
        ]);

        $this->assertSame(0, app(FormReviewEmailOutboxRecovery::class)->dispatchPending());
        $this->assertSame(FormReviewEmailOutbox::STATUS_SENT, $outbox->refresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_stale_sending_with_unconfirmed_log_is_quarantined_without_retry(): void
    {
        Queue::fake();
        $user = User::factory()->create(['email' => 'ambiguous-sending@example.com']);
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $deliveryKey = 'form-review-'.$answer->id.'-rejected';
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => $deliveryKey,
            'status' => FormReviewEmailOutbox::STATUS_SENDING,
            'attempts' => 1,
            'queued_at' => now()->subMinutes(20),
        ]);
        EmailLog::query()->create([
            'form_answer_id' => $answer->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'recipient_email' => $user->email,
            'notification_type' => EmailNotificationType::RegistrationRejected,
            'delivery_key' => $deliveryKey,
            'status' => EmailLogStatus::Queued,
        ]);

        $this->assertSame(0, app(FormReviewEmailOutboxRecovery::class)->dispatchPending());
        $this->assertSame(FormReviewEmailOutbox::STATUS_MANUAL_REVIEW, $outbox->refresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_stale_sending_without_log_is_retried_with_the_same_delivery_key(): void
    {
        Queue::fake();
        $user = User::factory()->create(['email' => 'retry-without-log@example.com']);
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $deliveryKey = 'form-review-'.$answer->id.'-rejected';
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => $deliveryKey,
            'status' => FormReviewEmailOutbox::STATUS_SENDING,
            'attempts' => 1,
            'queued_at' => now()->subMinutes(20),
        ]);

        $this->assertSame(1, app(FormReviewEmailOutboxRecovery::class)->dispatchPending());
        $this->assertSame(FormReviewEmailOutbox::STATUS_DISPATCHING, $outbox->refresh()->status);
        $this->assertSame(2, (int) $outbox->attempts);
        Queue::assertPushed(DispatchFormReviewEmailOutboxJob::class, function (DispatchFormReviewEmailOutboxJob $job) use ($outbox): bool {
            return $job->outboxId === (string) $outbox->id
                && $job->claimed
                && $job->claimAttempt === 2;
        });
    }

    public function test_stale_sending_with_failed_log_is_quarantined_without_retry(): void
    {
        Queue::fake();
        $user = User::factory()->create(['email' => 'failed-evidence@example.com']);
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
        ]);
        $deliveryKey = 'form-review-'.$answer->id.'-rejected';
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_REJECTED,
            'delivery_key' => $deliveryKey,
            'status' => FormReviewEmailOutbox::STATUS_SENDING,
            'attempts' => 1,
            'queued_at' => now()->subMinutes(20),
        ]);
        EmailLog::query()->create([
            'form_answer_id' => $answer->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'recipient_email' => $user->email,
            'notification_type' => EmailNotificationType::RegistrationRejected,
            'delivery_key' => $deliveryKey,
            'status' => EmailLogStatus::Failed,
            'error_message' => 'SMTP failure was recorded.',
        ]);

        $this->assertSame(0, app(FormReviewEmailOutboxRecovery::class)->dispatchPending());
        $this->assertSame(FormReviewEmailOutbox::STATUS_MANUAL_REVIEW, $outbox->refresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_individual_review_preserves_conflict_status_and_denies_unauthorized_actor(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('dashboard.events.forms.submissions.review', [
                'event' => $event,
                'form' => $form,
                'formAnswer' => $answer,
            ]), ['review_status' => FormAnswerReviewStatus::Rejected->value])
            ->assertStatus(409)
            ->assertJsonPath('current_status', FormAnswerReviewStatus::Accepted->value);

        $member = User::factory()->create();
        $member->assignRole('member');
        $pending = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $this->actingAs($member)
            ->patchJson(route('dashboard.events.forms.submissions.review', [
                'event' => $event,
                'form' => $form,
                'formAnswer' => $pending,
            ]), ['review_status' => FormAnswerReviewStatus::Accepted->value])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(FormAnswerReviewStatus::Pending, $pending->refresh()->review_status);
        $this->assertSame(0, FormReviewEmailOutbox::query()->count());
    }

    public function test_selected_ids_cannot_expand_to_another_form(): void
    {
        $actor = User::factory()->create();
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id, 'metadata' => ['registration_mode' => 'single']]);
        $otherForm = Form::factory()->create(['event_id' => $event->id, 'metadata' => ['registration_mode' => 'single']]);
        $selected = FormAnswer::factory()->create(['form_id' => $form->id, 'review_status' => FormAnswerReviewStatus::Pending]);
        $foreign = FormAnswer::factory()->create(['form_id' => $otherForm->id, 'review_status' => FormAnswerReviewStatus::Pending]);

        $this->expectException(HttpException::class);

        try {
            app(FormAnswerReviewService::class)->reviewMany(
                $event,
                $form,
                new Collection([$selected->id, $foreign->id]),
                FormAnswerReviewStatus::Accepted,
                $actor,
            );
        } finally {
            $this->assertSame(FormAnswerReviewStatus::Pending, $selected->refresh()->review_status);
            $this->assertSame(0, FormReviewEmailOutbox::query()->count());
        }
    }

    public function test_bulk_review_requires_explicit_bounded_unique_ids_and_decision(): void
    {
        $admin = $this->organizer();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);
        $url = route('dashboard.events.forms.submissions.bulk-review', compact('event', 'form'));

        $this->actingAs($admin)
            ->patchJson($url, ['review_status' => 'accepted'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('submission_ids');

        $this->actingAs($admin)
            ->patchJson($url, [
                'submission_ids' => [$answer->id, $answer->id],
                'review_status' => 'accepted',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('submission_ids.1');

        $this->actingAs($admin)
            ->patchJson($url, [
                'submission_ids' => array_map(static fn (): string => (string) Str::uuid(), range(1, 101)),
                'review_status' => 'accepted',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('submission_ids');

        $this->assertSame(FormAnswerReviewStatus::Pending, $answer->refresh()->review_status);
        $this->assertSame(0, FormReviewEmailOutbox::query()->count());
    }

    public function test_bulk_review_requires_an_organizer_and_route_form_scope(): void
    {
        $admin = $this->organizer();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create(['form_id' => $form->id]);
        $member = User::factory()->create();

        $this->actingAs($member)
            ->patchJson(route('dashboard.events.forms.submissions.bulk-review', compact('event', 'form')), [
                'submission_ids' => [$answer->id],
                'review_status' => 'accepted',
            ])
            ->assertRedirect(route('dashboard'));

        $otherEvent = Event::factory()->create(['created_by' => $admin->id]);
        $this->actingAs($admin)
            ->patchJson(route('dashboard.events.forms.submissions.bulk-review', [
                'event' => $otherEvent,
                'form' => $form,
            ]), [
                'submission_ids' => [$answer->id],
                'review_status' => 'accepted',
            ])
            ->assertNotFound();

        $this->assertSame(FormAnswerReviewStatus::Pending, $answer->refresh()->review_status);
        $this->assertSame(0, FormReviewEmailOutbox::query()->count());
    }

    public function test_bulk_review_rejects_foreign_and_ineligible_rows_atomically(): void
    {
        $admin = $this->organizer();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $otherForm = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $selected = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);
        $foreign = FormAnswer::factory()->create([
            'form_id' => $otherForm->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('dashboard.events.forms.submissions.bulk-review', compact('event', 'form')), [
                'submission_ids' => [$selected->id, $foreign->id],
                'review_status' => 'accepted',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'One or more selected submissions are not eligible for bulk review.');

        $member = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);
        $this->actingAs($admin)
            ->patchJson(route('dashboard.events.forms.submissions.bulk-review', compact('event', 'form')), [
                'submission_ids' => [$selected->id, $member->id],
                'review_status' => 'accepted',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only single-mode submissions without confirmation are eligible for bulk review.');

        $this->assertSame(FormAnswerReviewStatus::Pending, $selected->refresh()->review_status);
        $this->assertSame(FormAnswerReviewStatus::Pending, $member->refresh()->review_status);
        $this->assertSame(FormAnswerReviewStatus::Pending, $foreign->refresh()->review_status);
        $this->assertSame(0, FormReviewEmailOutbox::query()->count());
    }

    public function test_bulk_review_returns_conflict_without_mutating_a_stale_batch(): void
    {
        $admin = $this->organizer();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $pending = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);
        $stale = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('dashboard.events.forms.submissions.bulk-review', compact('event', 'form')), [
                'submission_ids' => [$pending->id, $stale->id],
                'review_status' => 'rejected',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'One or more selected submissions have already been reviewed.');

        $this->assertSame(FormAnswerReviewStatus::Pending, $pending->refresh()->review_status);
        $this->assertSame(FormAnswerReviewStatus::Accepted, $stale->refresh()->review_status);
        $this->assertSame(0, FormReviewEmailOutbox::query()->count());
    }

    public function test_bulk_review_returns_a_typed_success_summary_for_only_selected_rows(): void
    {
        Queue::fake();
        $admin = $this->organizer();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $answers = FormAnswer::factory()->count(2)->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);
        $unselected = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('dashboard.events.forms.submissions.bulk-review', compact('event', 'form')), [
                'submission_ids' => $answers->modelKeys(),
                'review_status' => 'accepted',
            ])
            ->assertOk()
            ->assertJson([
                'requested' => 2,
                'processed' => 2,
                'skipped' => 0,
                'conflicts' => 0,
                'queued_intents' => 2,
            ]);

        $this->assertSame(2, FormAnswer::query()->whereKey($answers->modelKeys())->where('review_status', 'accepted')->count());
        $this->assertSame(FormAnswerReviewStatus::Pending, $unselected->refresh()->review_status);
        $this->assertSame(2, FormReviewEmailOutbox::query()->count());
    }

    private function organizer(): User
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}

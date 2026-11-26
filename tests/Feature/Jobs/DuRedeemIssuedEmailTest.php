<?php

namespace Tests\Feature\Jobs;

use App\Enums\DuRedeemStatus;
use App\Enums\EmailLogStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Exceptions\RedactedMailDeliveryException;
use App\Jobs\SendDuRedeemIssuedJob;
use App\Mail\DuRedeemIssuedMail;
use App\Models\DuRedeemIssuance;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormReviewEmailOutbox;
use App\Models\User;
use App\Services\Registration\FormAnswerRecipientResolver;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

final class DuRedeemIssuedEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_du_issued_mail_contains_attendance_qr_and_redeem_code(): void
    {
        Mail::fake();
        [, , $answer, $outbox] = $this->issuedDelivery();

        (new SendDuRedeemIssuedJob((string) $answer->id, (string) $outbox->delivery_key))
            ->handle(app(RegistrationQrPngGenerator::class), app(FormAnswerRecipientResolver::class));

        Mail::assertSent(DuRedeemIssuedMail::class, function (DuRedeemIssuedMail $mail): bool {
            $rendered = $mail->render();

            return $mail->redeemCode === 'safe-test-code'
                && $mail->qrPngBinary !== ''
                && str_contains($rendered, 'safe-test-code')
                && str_contains($rendered, 'cid:qr-code.png')
                && count($mail->attachments()) === 1;
        });
    }

    public function test_missing_recipient_fails_du_email_delivery_without_sending(): void
    {
        Mail::fake();
        [, , $answer, $outbox] = $this->issuedDelivery(null);

        (new SendDuRedeemIssuedJob((string) $answer->id, (string) $outbox->delivery_key))
            ->handle(app(RegistrationQrPngGenerator::class), app(FormAnswerRecipientResolver::class));

        Mail::assertNothingSent();
        $this->assertSame(FormReviewEmailOutbox::STATUS_FAILED, $outbox->refresh()->status);
        $this->assertSame(EmailLogStatus::Failed, EmailLog::query()->where('delivery_key', $outbox->delivery_key)->firstOrFail()->status);
        $this->assertSame(DuRedeemStatus::Issued, $answer->duRedeemIssuance()->firstOrFail()->status);
    }

    public function test_send_failure_fails_du_delivery_but_preserves_issued_code(): void
    {
        [, , $answer, $outbox] = $this->issuedDelivery();
        Mail::shouldReceive('to->send')->once()->andThrow(new RuntimeException('SMTP failed for safe-test-recipient@example.test'));

        try {
            (new SendDuRedeemIssuedJob((string) $answer->id, (string) $outbox->delivery_key))
                ->handle(app(RegistrationQrPngGenerator::class), app(FormAnswerRecipientResolver::class));
            self::fail('DU mail delivery should fail.');
        } catch (RedactedMailDeliveryException) {
            // Queue worker retries the same delivery key after transport failure.
        }

        $issuance = $answer->duRedeemIssuance()->firstOrFail();
        $this->assertSame(DuRedeemStatus::Issued, $issuance->status);
        $this->assertSame('safe-test-code', $issuance->redeem_code);
        $this->assertSame(FormReviewEmailOutbox::STATUS_FAILED, $outbox->refresh()->status);
        $this->assertSame(EmailLogStatus::Failed, EmailLog::query()->where('delivery_key', $outbox->delivery_key)->firstOrFail()->status);
    }

    public function test_du_issued_job_defines_retry_backoff_and_timeout(): void
    {
        [, , $answer, $outbox] = $this->issuedDelivery();
        $job = new SendDuRedeemIssuedJob((string) $answer->id, (string) $outbox->delivery_key);

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertNotEmpty($job->backoff);
    }

    public function test_repeated_handle_sends_du_issued_mail_once_and_keeps_single_sent_email_log(): void
    {
        Mail::fake();
        [, , $answer, $outbox] = $this->issuedDelivery();
        $job = new SendDuRedeemIssuedJob((string) $answer->id, (string) $outbox->delivery_key);

        $job->handle(app(RegistrationQrPngGenerator::class), app(FormAnswerRecipientResolver::class));
        $job->handle(app(RegistrationQrPngGenerator::class), app(FormAnswerRecipientResolver::class));

        Mail::assertSent(DuRedeemIssuedMail::class, 1);
        $this->assertSame(1, EmailLog::query()->where('delivery_key', $outbox->delivery_key)->count());
        $this->assertSame(EmailLogStatus::Sent, EmailLog::query()->where('delivery_key', $outbox->delivery_key)->firstOrFail()->status);
    }

    /** @return array{0: Event, 1: Form, 2: FormAnswer, 3: FormReviewEmailOutbox} */
    private function issuedDelivery(?string $email = 'safe-test-recipient@example.test'): array
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single'],
        ]);
        $user = $email === null ? null : User::factory()->create(['email' => $email]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user?->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'REG-TEST-001',
            'answers' => ['du_course_uid' => 'course-test'],
            'invited_email' => null,
        ]);
        DuRedeemIssuance::query()->create([
            'form_answer_id' => $answer->id,
            'course_uid' => 'course-test',
            'status' => DuRedeemStatus::Issued,
            'redeem_code' => 'safe-test-code',
            'issued_at' => now(),
        ]);
        $outbox = FormReviewEmailOutbox::query()->create([
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_DU_ISSUED,
            'delivery_key' => 'form-review-'.$answer->id.'-du-issued',
            'status' => FormReviewEmailOutbox::STATUS_QUEUED,
            'available_at' => now(),
        ]);

        return [$event, $form, $answer, $outbox];
    }
}

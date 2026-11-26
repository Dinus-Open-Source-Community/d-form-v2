<?php

namespace Tests\Feature\Jobs;

use App\Enums\EmailNotificationType;
use App\Mail\AnswerCollectionReceivedMail;
use App\Mail\FeedbackReceivedMail;
use App\Mail\RegistrationConfirmationMail;
use App\Mail\DkelasAttendanceCardMail;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Registration\FormReceiptContext;
use App\Services\Registration\FormReceiptMailResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormReceiptMailResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_uses_the_existing_confirmation_mail(): void
    {
        $submission = FormAnswer::factory()->create();
        $resolver = app(FormReceiptMailResolver::class);

        $this->assertInstanceOf(RegistrationConfirmationMail::class, $resolver->make($submission, $this->context()));
        $this->assertSame(EmailNotificationType::RegistrationSubmitted, $resolver->notificationType($submission->form));
    }

    public function test_answer_collection_and_feedback_get_their_own_mail(): void
    {
        $collection = FormAnswer::factory()->create(['form_id' => Form::factory()->create(['metadata' => ['purpose' => 'answer_collection']])->id]);
        $feedback = FormAnswer::factory()->create(['form_id' => Form::factory()->create(['metadata' => ['purpose' => 'feedback']])->id]);

        $resolver = app(FormReceiptMailResolver::class);

        $this->assertInstanceOf(AnswerCollectionReceivedMail::class, $resolver->make($collection, $this->context()));
        $this->assertInstanceOf(FeedbackReceivedMail::class, $resolver->make($feedback, $this->context()));
        $this->assertSame(EmailNotificationType::AnswerCollectionReceived, $resolver->notificationType($collection->form));
        $this->assertSame(EmailNotificationType::FeedbackReceived, $resolver->notificationType($feedback->form));
        $collectionMail = $resolver->make($collection, $this->context());
        $feedbackMail = $resolver->make($feedback, $this->context());
        $this->assertStringContainsString('Jawabanmu sudah kami terima', $collectionMail->render());
        $this->assertStringContainsString('Ringkasan isian ada di bawah', $collectionMail->render());
        $this->assertStringNotContainsString('cid:qr-code.png', $collectionMail->render());
        $this->assertStringContainsString('Terima kasih sudah mengisi', $feedbackMail->render());
        $this->assertStringNotContainsString('cid:qr-code.png', $feedbackMail->render());
    }

    public function test_other_purpose_keeps_the_legacy_confirmation_mail(): void
    {
        $submission = FormAnswer::factory()->create(['form_id' => Form::factory()->create(['metadata' => ['purpose' => 'other']])->id]);

        $this->assertInstanceOf(RegistrationConfirmationMail::class, app(FormReceiptMailResolver::class)->make($submission, $this->context()));
    }

    public function test_d_kelas_gets_a_qr_mail_without_review_status_gate(): void
    {
        $submission = FormAnswer::factory()->create([
            'form_id' => Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']])->id,
            'review_status' => 'pending',
        ]);
        $context = new FormReceiptContext(['Name' => 'Value'], 'qr-binary', ['2026-12-01']);
        $resolver = app(FormReceiptMailResolver::class);

        $mail = $resolver->make($submission, $context);

        $this->assertInstanceOf(DkelasAttendanceCardMail::class, $mail);
        $this->assertSame(EmailNotificationType::ClassAttendanceCard, $resolver->notificationType($submission->form));
        $this->assertSame('qr-binary', $mail->context->qrPngBinary);
        $rendered = $mail->render();
        $this->assertStringContainsString('Kartu absen kelas', $rendered);
        $this->assertStringContainsString('2026-12-01', $rendered);
        $this->assertStringContainsString('cid:qr-code.png', $rendered);
        $this->assertCount(1, $mail->attachments());
    }

    private function context(): FormReceiptContext
    {
        return new FormReceiptContext([], null, []);
    }
}

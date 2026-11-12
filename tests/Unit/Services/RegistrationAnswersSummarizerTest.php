<?php

namespace Tests\Unit\Services;

use App\Enums\RegistrationRole;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use App\Services\Registration\RegistrationAnswersSummarizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationAnswersSummarizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_member_sees_duplicatable_and_shared_fields(): void
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['registration_mode' => 'bundle', 'max_team_size' => 2],
        ]);

        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'team_name',
            'label' => 'Nama tim',
            'order' => 1,
            'input_type' => 'input',
            'metadata' => [
                'type' => 'text',
                'rules' => [],
            ],
        ]);

        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'full_name',
            'label' => 'Nama lengkap',
            'order' => 2,
            'input_type' => 'input',
            'metadata' => [
                'type' => 'text',
                'rules' => [],
                'duplicatable' => true,
            ],
        ]);

        $user = User::factory()->create();
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'registration_role' => RegistrationRole::Member,
            'answers' => [
                'team_name' => 'Nama tim dari ketua',
                'full_name' => 'Nama peserta ini',
            ],
        ]);

        $summary = app(RegistrationAnswersSummarizer::class)->summarizeForPortal($answer);

        $this->assertArrayHasKey('Nama lengkap', $summary);
        $this->assertSame('Nama peserta ini', $summary['Nama lengkap']);
        $this->assertArrayHasKey('Nama tim', $summary);
        $this->assertSame('Nama tim dari ketua', $summary['Nama tim']);
    }

    public function test_file_upload_field_returns_temporary_signed_url(): void
    {
        Storage::fake('local');

        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $path = 'form-uploads/'.$form->id.'/bukti.png';
        Storage::disk('local')->put($path, 'fake-image');

        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'payment_proof',
            'label' => 'Bukti pembayaran',
            'order' => 1,
            'input_type' => 'fileUpload',
            'metadata' => [
                'rules' => ['required' => true],
            ],
        ]);

        $user = User::factory()->create();
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'answers' => ['payment_proof' => $path],
        ]);

        $summary = app(RegistrationAnswersSummarizer::class)->summarize($answer);

        $this->assertArrayHasKey('Bukti pembayaran', $summary);
        $this->assertStringContainsString('/form-answers/'.$answer->id.'/files/payment_proof/bukti.png', $summary['Bukti pembayaran']);
        $this->assertStringContainsString('signature=', $summary['Bukti pembayaran']);
        $this->assertStringNotContainsString('/storage/', $summary['Bukti pembayaran']);
    }

    public function test_portal_file_upload_field_returns_authenticated_route(): void
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $path = 'form-uploads/'.$form->id.'/bukti.png';

        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'payment_proof',
            'label' => 'Bukti pembayaran',
            'order' => 1,
            'input_type' => 'fileUpload',
            'metadata' => [],
        ]);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'answers' => ['payment_proof' => $path],
        ]);

        $summary = app(RegistrationAnswersSummarizer::class)->summarizeForPortal($answer);

        $this->assertSame(
            '/form-answers/'.$answer->id.'/files/payment_proof/bukti.png',
            $summary['Bukti pembayaran'],
        );
    }
    public function test_multiple_file_upload_summary_uses_one_authorized_url_per_file_and_explicit_delimiter(): void
    {
        Storage::fake('local');

        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $first = 'form-uploads/'.$form->id.'/first.pdf';
        $second = 'form-uploads/'.$form->id.'/second.pdf';
        Storage::disk('local')->put($first, 'first');
        Storage::disk('local')->put($second, 'second');

        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'documents',
            'label' => 'Documents',
            'order' => 1,
            'input_type' => 'fileUpload',
            'metadata' => ['multiple' => true],
        ]);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'answers' => ['documents' => [$first, $second]],
        ]);

        $summary = app(RegistrationAnswersSummarizer::class)->summarize($answer);

        self::assertStringContainsString('/form-answers/'.$answer->id.'/files/documents/first.pdf', $summary['Documents']);
        self::assertStringContainsString('/form-answers/'.$answer->id.'/files/documents/second.pdf', $summary['Documents']);
        self::assertSame(2, substr_count($summary['Documents'], 'signature='));
        self::assertStringContainsString('; ', $summary['Documents']);
        self::assertStringNotContainsString('form-uploads/', $summary['Documents']);
    }
}

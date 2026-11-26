<?php

namespace Tests\Feature\Forms;

use App\Enums\FormAnswerReviewStatus;
use App\Models\DuCourse;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\FormReviewEmailOutbox;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DuRedeemFixture;
use Tests\TestCase;

/** Aksi "Terbitkan kode" per jawaban lewat endpoint du-issuance yang sudah ada. */
final class DuDrawerIssuanceTest extends TestCase
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
            'metadata' => ['purpose' => 'registration', 'registration_mode' => 'single'],
        ]);
        FormField::factory()->create([
            'form_id' => $this->form->id,
            'name' => 'class',
            'label' => 'Class',
            'input_type' => 'selectInput',
            'order' => 1,
            'metadata' => [
                'du_redeem_enabled' => true,
                'optionChoices' => [
                    ['id' => 'o1', 'type' => 'text', 'label' => 'AI Engineer'],
                    ['id' => 'o2', 'type' => 'text', 'label' => 'DevOps'],
                ],
                'rules' => ['required' => true, 'in' => 'AI Engineer,DevOps'],
                'du_option_map' => [
                    'AI Engineer' => ['course_uid' => 'course-ai', 'course_title' => 'AI Engineering'],
                ],
            ],
        ]);
    }

    public function test_mapped_custom_field_answer_is_issued_and_queues_the_email_intent(): void
    {
        $this->seedCourse('course-ai', 'AI Engineering');
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => DuRedeemFixture::success('CODE-DRAWER', 'course-ai'),
        ], 201)]);
        $answer = $this->answer('AI Engineer', FormAnswerReviewStatus::Accepted);

        $this->actingAs($this->admin)
            ->postJson($this->issuePath($answer), [])
            ->assertOk()
            ->assertJson(['status' => 'issued', 'code' => 'CODE-DRAWER']);

        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => 'issued',
            'course_uid' => 'course-ai',
            'redeem_code' => 'CODE-DRAWER',
        ]);
        $this->assertDatabaseHas('form_review_email_outboxes', [
            'form_answer_id' => $answer->id,
            'action' => FormReviewEmailOutbox::ACTION_DU_ISSUED,
            'delivery_key' => 'form-review-'.$answer->id.'-du-issued',
        ]);
        Http::assertSent(fn (Request $request): bool => $request['course_uid'] === 'course-ai');
    }

    public function test_unmapped_answer_fails_with_the_safe_message_without_calling_du(): void
    {
        Http::fake();
        $answer = $this->answer('DevOps', FormAnswerReviewStatus::Accepted);

        $this->actingAs($this->admin)
            ->postJson($this->issuePath($answer), [])
            ->assertOk()
            ->assertJson(['status' => 'failed', 'message' => 'Kelas belum terhubung ke course DU.']);

        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => 'failed',
        ]);
        Http::assertNothingSent();
    }

    public function test_pending_answer_is_not_issued_and_creates_no_row(): void
    {
        Http::fake();
        $this->seedCourse('course-ai', 'AI Engineering');
        $answer = $this->answer('AI Engineer', FormAnswerReviewStatus::Pending);

        $this->actingAs($this->admin)
            ->postJson($this->issuePath($answer), [])
            ->assertOk()
            ->assertJson(['status' => 'failed', 'message' => 'Submission is not accepted.']);

        $this->assertDatabaseMissing('du_redeem_issuances', ['form_answer_id' => $answer->id]);
        Http::assertNothingSent();
    }

    private function answer(string $classValue, FormAnswerReviewStatus $status): FormAnswer
    {
        return FormAnswer::factory()->create([
            'form_id' => $this->form->id,
            'answers' => ['class' => $classValue],
            'review_status' => $status,
        ]);
    }

    private function issuePath(FormAnswer $answer): string
    {
        return route('dashboard.events.forms.submissions.du-issuance', [
            'event' => $this->event,
            'form' => $this->form,
            'formAnswer' => $answer,
        ]);
    }

    private function seedCourse(string $uid, string $title): void
    {
        DuCourse::factory()->create(['uid' => $uid, 'title' => $title, 'is_active' => true]);
    }
}

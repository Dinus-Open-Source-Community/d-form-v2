<?php

namespace Tests\Feature\Forms;

use App\Enums\DuRedeemStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Models\DuCourse;
use App\Models\DuRedeemIssuance;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use App\Services\Du\DuRedeemIssuanceService;
use App\Services\Form\FormAnswerReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\DuRedeemFixture;
use Tests\TestCase;

final class DuRedeemRegistrationFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_form_with_a_mapped_custom_class_issues_the_mapped_course(): void
    {
        $this->seedCourse('course-ai', 'AI Engineering');
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => DuRedeemFixture::success('CODE', 'course-ai'),
        ], 201)]);
        [$event, $form] = $this->registrationFormWithClassField();
        $answer = $this->answer($form, ['class' => 'AI Engineer']);

        $result = app(FormAnswerReviewService::class)->reviewOne(
            $event,
            $form,
            $answer,
            FormAnswerReviewStatus::Accepted,
            User::factory()->create(),
        );

        $this->assertSame(DuRedeemStatus::Issued, $result['du_issuance']->status);
        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => 'issued',
            'course_uid' => 'course-ai',
            'redeem_code' => 'CODE',
            'du_uid' => 'redeem-CODE',
            'course_title' => 'Test Course',
            'target_email' => 'recipient@example.test',
            'max_uses' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);
        Http::assertSent(fn (Request $request): bool => $request['course_uid'] === 'course-ai');
    }

    public function test_unmapped_custom_class_fails_closed_without_calling_du(): void
    {
        Http::fake();
        [$event, $form] = $this->registrationFormWithClassField();
        $answer = $this->answer($form, ['class' => 'UI/UX']);

        $result = app(FormAnswerReviewService::class)->reviewOne(
            $event,
            $form,
            $answer,
            FormAnswerReviewStatus::Accepted,
            User::factory()->create(),
        );

        $this->assertSame(DuRedeemStatus::Failed, $result['du_issuance']->status);
        $this->assertSame('Kelas belum terhubung ke course DU.', $result['du_issuance']->message);
        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => 'failed',
        ]);
        Http::assertNothingSent();
    }

    public function test_label_change_on_the_custom_field_does_not_break_existing_answers(): void
    {
        $this->seedCourse('course-ai', 'AI Engineering');
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => DuRedeemFixture::success('CODE', 'course-ai'),
        ], 201)]);
        [, $form, $field] = $this->registrationFormWithClassField();
        $first = $this->answer($form, ['class' => 'AI Engineer'], FormAnswerReviewStatus::Accepted);

        $firstIssuance = app(DuRedeemIssuanceService::class)->issueAccepted($first);
        $this->assertSame(DuRedeemStatus::Issued, $firstIssuance->status);

        $field->update(['label' => 'Kelas']);

        $second = $this->answer($form, ['class' => 'AI Engineer'], FormAnswerReviewStatus::Accepted);
        $secondIssuance = app(DuRedeemIssuanceService::class)->issueAccepted($second);

        $this->assertSame(DuRedeemStatus::Issued, $secondIssuance->status);
        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $second->id,
            'status' => 'issued',
            'course_uid' => 'course-ai',
        ]);
    }

    public function test_mapped_course_missing_from_cache_fails_closed(): void
    {
        Http::fake();
        [$event, $form] = $this->registrationFormWithClassField([
            'AI Engineer' => ['course_uid' => 'course-missing', 'course_title' => 'Missing course'],
        ]);
        $answer = $this->answer($form, ['class' => 'AI Engineer']);

        $result = app(FormAnswerReviewService::class)->reviewOne(
            $event,
            $form,
            $answer,
            FormAnswerReviewStatus::Accepted,
            User::factory()->create(),
        );

        $this->assertSame(DuRedeemStatus::Failed, $result['du_issuance']->status);
        $this->assertSame('Kelas belum terhubung ke course DU.', $result['du_issuance']->message);
        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => 'failed',
        ]);
        Http::assertNothingSent();
    }

    public function test_bulk_review_is_rejected_for_a_du_enabled_registration_form(): void
    {
        [$event, $form] = $this->registrationFormWithClassField();
        $answer = $this->answer($form, ['class' => 'AI Engineer']);

        try {
            app(FormAnswerReviewService::class)->reviewMany(
                $event,
                $form,
                new Collection([$answer->id]),
                FormAnswerReviewStatus::Accepted,
                User::factory()->create(),
            );
            self::fail('DU-enabled registration bulk review should fail.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_existing_unknown_issuance_is_never_retried(): void
    {
        $this->seedCourse('course-ai', 'AI Engineering');
        Http::fake();
        [, $form] = $this->registrationFormWithClassField();
        $answer = $this->answer($form, ['class' => 'AI Engineer'], FormAnswerReviewStatus::Accepted);
        $issuance = DuRedeemIssuance::query()->create([
            'form_answer_id' => $answer->id,
            'course_uid' => 'course-ai',
            'status' => DuRedeemStatus::Unknown,
            'attempted_at' => now(),
            'attempt_count' => 1,
        ]);

        $result = app(DuRedeemIssuanceService::class)->issueAccepted($answer);

        $this->assertSame(DuRedeemStatus::Unknown, $result->status);
        $this->assertSame('Issuance is already claimed.', $result->message);
        $this->assertSame($issuance->id, $answer->duRedeemIssuance()->firstOrFail()->id);
        $this->assertSame(1, DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->count());
        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'status' => 'unknown',
            'attempt_count' => 1,
        ]);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, array{course_uid: string, course_title: string}>|null  $optionMap
     * @return array{0: Event, 1: Form, 2: FormField}
     */
    private function registrationFormWithClassField(?array $optionMap = null): array
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'registration', 'registration_mode' => 'single'],
        ]);
        $field = FormField::factory()->create([
            'form_id' => $form->id,
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
                'du_option_map' => $optionMap ?? [
                    'AI Engineer' => ['course_uid' => 'course-ai', 'course_title' => 'AI Engineering'],
                ],
            ],
        ]);

        return [$event, $form, $field];
    }

    /** @param  array<string, mixed>  $answers */
    private function answer(
        Form $form,
        array $answers,
        FormAnswerReviewStatus $status = FormAnswerReviewStatus::Pending,
    ): FormAnswer {
        return FormAnswer::factory()->create([
            'form_id' => $form->id,
            'answers' => $answers,
            'review_status' => $status,
        ]);
    }

    private function seedCourse(string $uid, string $title): void
    {
        DuCourse::factory()->create(['uid' => $uid, 'title' => $title, 'is_active' => true]);
    }
}

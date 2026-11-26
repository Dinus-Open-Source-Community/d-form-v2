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
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Payload drawer jawaban: status DU, nilai kelas, mapping, saran, kode, dan katalog course. */
final class DuAnswerDrawerPayloadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->event = Event::factory()->create(['created_by' => $this->admin->id]);
    }

    public function test_mapped_custom_class_answer_exposes_the_mapping_and_can_issue(): void
    {
        [$form, $field] = $this->registrationFormWithClassField();
        $this->seedCourse('course-ai', 'AI Engineering', true);
        $this->seedCourse('course-beta', 'Beta', true);
        $this->seedCourse('course-off', 'Gamma', false);
        $answer = $this->answer($form, ['class' => 'AI Engineer'], FormAnswerReviewStatus::Accepted);

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.id', $answer->id)
                ->where('submissions.0.du.enabled', true)
                ->where('submissions.0.du.class_field.id', $field->id)
                ->where('submissions.0.du.class_field.name', 'class')
                ->where('submissions.0.du.class_field.label', 'Class')
                ->where('submissions.0.du.class_value', 'AI Engineer')
                ->where('submissions.0.du.mapping.course_uid', 'course-ai')
                ->where('submissions.0.du.mapping.course_title', 'AI Engineering')
                ->where('submissions.0.du.mapping.is_active', true)
                ->where('submissions.0.du.suggestion', null)
                ->where('submissions.0.du.issuance', null)
                ->where('submissions.0.du.can_issue', true)
                // Hanya course aktif, urut judul.
                ->has('duCatalog', 2)
                ->where('duCatalog.0.uid', 'course-ai')
                ->where('duCatalog.0.title', 'AI Engineering')
                ->where('duCatalog.1.uid', 'course-beta'));
    }

    public function test_unmapped_answer_with_an_exact_suggestion_cannot_issue(): void
    {
        [$form] = $this->registrationFormWithClassField();
        $this->seedCourse('course-devops', 'DevOps', true);
        $this->answer($form, ['class' => 'DevOps'], FormAnswerReviewStatus::Accepted);

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.du.class_value', 'DevOps')
                ->where('submissions.0.du.mapping', null)
                ->where('submissions.0.du.suggestion.course_uid', 'course-devops')
                ->where('submissions.0.du.suggestion.course_title', 'DevOps')
                ->where('submissions.0.du.suggestion.is_active', true)
                ->where('submissions.0.du.can_issue', false));
    }

    public function test_issued_answer_exposes_the_code_and_cannot_issue_again(): void
    {
        [$form] = $this->registrationFormWithClassField();
        $this->seedCourse('course-ai', 'AI Engineering', true);
        $answer = $this->answer($form, ['class' => 'AI Engineer'], FormAnswerReviewStatus::Accepted);
        DuRedeemIssuance::query()->create([
            'form_answer_id' => $answer->id,
            'course_uid' => 'course-ai',
            'status' => DuRedeemStatus::Issued,
            'redeem_code' => 'DU-ABC-123',
            'issued_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.du.mapping.course_uid', 'course-ai')
                ->where('submissions.0.du.issuance.status', 'issued')
                ->where('submissions.0.du.issuance.code', 'DU-ABC-123')
                ->where('submissions.0.du.issuance.message', null)
                ->where('submissions.0.du.can_issue', false));
    }

    public function test_mapping_to_a_uid_missing_from_the_cache_is_not_connected(): void
    {
        [$form] = $this->registrationFormWithClassField([
            'AI Engineer' => ['course_uid' => 'course-missing', 'course_title' => 'Missing'],
        ]);
        // Title sama tapi tidak aktif: saran hanya dari katalog aktif.
        $this->seedCourse('course-stale', 'AI Engineer', false);
        $this->answer($form, ['class' => 'AI Engineer'], FormAnswerReviewStatus::Accepted);

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.du.class_value', 'AI Engineer')
                ->where('submissions.0.du.mapping', null)
                ->where('submissions.0.du.suggestion', null)
                ->where('submissions.0.du.can_issue', false));
    }

    public function test_mapping_to_an_inactive_course_stays_connected_and_issueable(): void
    {
        [$form] = $this->registrationFormWithClassField();
        $this->seedCourse('course-ai', 'AI Engineering', false);
        $this->answer($form, ['class' => 'AI Engineer'], FormAnswerReviewStatus::Accepted);

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.du.mapping.course_uid', 'course-ai')
                ->where('submissions.0.du.mapping.course_title', 'AI Engineering')
                ->where('submissions.0.du.mapping.is_active', false)
                ->where('submissions.0.du.can_issue', true)
                // Course tidak aktif tidak pernah masuk katalog picker.
                ->has('duCatalog', 0));
    }

    public function test_can_issue_only_for_pending_or_failed_issuances(): void
    {
        [$form] = $this->registrationFormWithClassField();
        $this->seedCourse('course-ai', 'AI Engineering', true);
        $answer = $this->answer($form, ['class' => 'AI Engineer'], FormAnswerReviewStatus::Accepted);
        $issuance = DuRedeemIssuance::query()->create([
            'form_answer_id' => $answer->id,
            'course_uid' => 'course-ai',
            'status' => DuRedeemStatus::Failed,
            'failure_reason' => 'DU rejected request.',
        ]);

        foreach ([DuRedeemStatus::Failed, DuRedeemStatus::Pending] as $status) {
            $issuance->update(['status' => $status]);

            $this->actingAs($this->admin)
                ->get($this->showPath($form))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('submissions.0.du.issuance.status', $status->value)
                    ->where('submissions.0.du.can_issue', true));
        }

        $issuance->update(['status' => DuRedeemStatus::Unknown]);

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('submissions.0.du.issuance.status', 'unknown')
                ->where('submissions.0.du.can_issue', false));
    }

    public function test_d_kelas_system_field_answer_resolves_the_cached_course(): void
    {
        $form = $this->dKelasForm();
        $this->seedCourse('course-ai', 'AI Engineering', true);
        $this->answer($form, ['du_course_uid' => 'course-ai'], FormAnswerReviewStatus::Accepted);

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.du.class_field.name', 'du_course_uid')
                ->where('submissions.0.du.class_value', 'course-ai')
                ->where('submissions.0.du.mapping.course_uid', 'course-ai')
                ->where('submissions.0.du.mapping.course_title', 'AI Engineering')
                ->where('submissions.0.du.mapping.is_active', true)
                ->where('submissions.0.du.suggestion', null)
                ->where('submissions.0.du.can_issue', true));
    }

    public function test_d_kelas_system_field_answer_resolves_an_inactive_cached_course(): void
    {
        $form = $this->dKelasForm();
        $this->seedCourse('course-off', 'Old Course', false);
        $this->answer($form, ['du_course_uid' => 'course-off'], FormAnswerReviewStatus::Accepted);

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.du.mapping.course_uid', 'course-off')
                ->where('submissions.0.du.mapping.is_active', false)
                ->where('submissions.0.du.can_issue', true)
                ->has('duCatalog', 0));
    }

    public function test_du_catalog_is_loaded_once_for_all_answers(): void
    {
        $form = $this->dKelasForm();
        $this->seedCourse('course-ai', 'AI Engineering', true);
        $this->answer($form, ['du_course_uid' => 'course-ai'], FormAnswerReviewStatus::Accepted);
        $this->answer($form, ['du_course_uid' => 'course-ai'], FormAnswerReviewStatus::Pending);

        $courseQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$courseQueries): void {
            if (str_contains($query->sql, 'du_courses')) {
                $courseQueries++;
            }
        });

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk();

        // Satu query cache untuk drawer dan opsi field sistem.
        $this->assertSame(1, $courseQueries);
    }

    public function test_non_du_form_has_a_null_payload_and_no_catalog(): void
    {
        $form = Form::factory()->create([
            'event_id' => $this->event->id,
            'metadata' => ['purpose' => 'registration', 'registration_mode' => 'single'],
        ]);
        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'class',
            'label' => 'Class',
            'input_type' => 'selectInput',
            'order' => 1,
            'metadata' => ['rules' => ['required' => true, 'in' => 'AI Engineer,DevOps']],
        ]);
        $this->seedCourse('course-ai', 'AI Engineering', true);
        $this->answer($form, ['class' => 'AI Engineer'], FormAnswerReviewStatus::Accepted);

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.du', null)
                ->missing('duCatalog'));
    }

    public function test_disabled_flag_keeps_an_issued_code_visible_in_the_payload(): void
    {
        [$form, $field] = $this->registrationFormWithClassField();
        $field->update(['metadata' => array_merge($field->metadata->all(), ['du_redeem_enabled' => false])]);
        $this->seedCourse('course-ai', 'AI Engineering', true);
        $answer = $this->answer($form, ['class' => 'AI Engineer'], FormAnswerReviewStatus::Accepted);
        DuRedeemIssuance::query()->create([
            'form_answer_id' => $answer->id,
            'course_uid' => 'course-ai',
            'status' => DuRedeemStatus::Issued,
            'redeem_code' => 'DU-OFF-001',
            'issued_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.du.enabled', false)
                ->where('submissions.0.du.class_field', null)
                ->where('submissions.0.du.class_value', null)
                ->where('submissions.0.du.mapping', null)
                ->where('submissions.0.du.issuance.status', 'issued')
                ->where('submissions.0.du.issuance.code', 'DU-OFF-001')
                ->where('submissions.0.du.can_issue', false)
                ->missing('duCatalog'));
    }

    public function test_disabled_flag_without_an_issuance_still_has_a_null_payload(): void
    {
        [$form, $field] = $this->registrationFormWithClassField();
        $field->update(['metadata' => array_merge($field->metadata->all(), ['du_redeem_enabled' => false])]);
        $this->answer($form, ['class' => 'AI Engineer'], FormAnswerReviewStatus::Accepted);

        $this->actingAs($this->admin)
            ->get($this->showPath($form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.du', null)
                ->missing('duCatalog'));
    }

    /**
     * @param  array<string, array{course_uid: string, course_title: string}>|null  $optionMap
     * @return array{0: Form, 1: FormField}
     */
    private function registrationFormWithClassField(?array $optionMap = null): array
    {
        $form = Form::factory()->create([
            'event_id' => $this->event->id,
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

        return [$form, $field];
    }

    private function dKelasForm(): Form
    {
        return Form::factory()->create([
            'event_id' => $this->event->id,
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single'],
        ]);
    }

    /** @param  array<string, mixed>  $answers */
    private function answer(Form $form, array $answers, FormAnswerReviewStatus $status): FormAnswer
    {
        return FormAnswer::factory()->create([
            'form_id' => $form->id,
            'answers' => $answers,
            'review_status' => $status,
        ]);
    }

    private function showPath(Form $form): string
    {
        return route('dashboard.events.forms.show', [
            'event' => $this->event,
            'form' => $form,
            'tab' => 'jawaban',
        ]);
    }

    private function seedCourse(string $uid, string $title, bool $active): void
    {
        DuCourse::factory()->create(['uid' => $uid, 'title' => $title, 'is_active' => $active]);
    }
}

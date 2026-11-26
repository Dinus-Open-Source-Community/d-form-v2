<?php

namespace Tests\Feature\Forms;

use App\Enums\FormPurpose;
use App\Enums\FormSubmissionMode;
use App\Models\DuCourse;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\Registration\FormSubmissionContext;
use App\Services\Registration\FormSubmissionOrchestrator;
use App\Services\Registration\FormSubmissionPayload;
use App\Services\Registration\FormSubmissionRules;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DuCourseSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidCourseInputs(): array
    {
        return [
            'missing' => [[]],
            'foreign' => [['du_course_uid' => 'foreign']],
            'non scalar' => [['du_course_uid' => ['valid']]],
        ];
    }
    private function fields(): Collection
    {
        return new Collection([
            new FormField(['name' => 'du_course_uid', 'input_type' => 'selectInput', 'metadata' => []]),
        ]);
    }

    private function duForm(): Form
    {
        return new Form(['metadata' => ['purpose' => FormPurpose::DKelas->value]]);
    }

    #[DataProvider('invalidCourseInputs')]
    public function test_invalid_course_uid_is_rejected(array $input): void
    {
        $this->expectException(ValidationException::class);
        FormSubmissionRules::validate($input, $this->fields(), FormSubmissionMode::Single, 1, ['valid']);
    }

    public function test_valid_single_course_uid_is_accepted_without_upstream_payload(): void
    {
        $validated = FormSubmissionRules::validate(['du_course_uid' => 'valid'], $this->fields(), FormSubmissionMode::Single, 1, ['valid']);
        self::assertSame(['du_course_uid' => 'valid'], $validated);
        self::assertArrayNotHasKey('response', $validated);
        self::assertArrayNotHasKey('secret', $validated);
    }

    public function test_bundle_accepts_different_course_uids_per_participant(): void
    {
        $input = ['du_course_uid' => 'leader', 'bundle__du_course_uid__0' => 'member'];
        $validated = FormSubmissionRules::validate($input, $this->fields(), FormSubmissionMode::Bundle, 2, ['leader', 'member']);
        self::assertSame($input, $validated);
    }

    public function test_orchestrator_maps_bundle_course_titles_from_the_cache(): void
    {
        DuCourse::factory()->create(['uid' => 'course-leader', 'title' => 'Kelas Leader', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-member', 'title' => 'Kelas Member', 'is_active' => true]);
        Http::fake();
        $user = User::factory()->create();
        $form = $this->duForm();
        $context = new FormSubmissionContext(
            ['du_course_uid' => 'Kelas Leader', 'bundle__du_course_uid__0' => 'Kelas Member', 'team_member_emails' => ['member@example.test']],
            $form,
            $user,
            $this->fields(),
            FormSubmissionMode::Bundle,
            2,
        );

        $payload = app(FormSubmissionOrchestrator::class)->validateAndMap($context);
        self::assertSame('Kelas Leader', $payload->answers['leader']['du_course_uid']);
        self::assertSame('Kelas Member', $payload->answers['members'][0]['du_course_uid']);
        Http::assertNothingSent();
    }

    public function test_ambiguous_course_title_is_rejected_with_422(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Kelas A', 'is_active' => true]);
        Http::fake();
        $context = new FormSubmissionContext(
            ['du_course_uid' => 'Kelas A'],
            $this->duForm(),
            User::factory()->create(),
            $this->fields(),
            FormSubmissionMode::Single,
            1,
        );

        try {
            app(FormSubmissionOrchestrator::class)->validateAndMap($context);
            self::fail('Judul kelas yang ambigu harus ditolak.');
        } catch (ValidationException $exception) {
            self::assertSame(['du_course_uid' => [__('Kelas DU yang dipilih tidak tersedia.')]], $exception->errors());
        }

        Http::assertNothingSent();
    }

    public function test_bundle_member_slot_with_unknown_course_is_rejected(): void
    {
        DuCourse::factory()->create(['uid' => 'course-leader', 'title' => 'Kelas Leader', 'is_active' => true]);
        Http::fake();
        $context = new FormSubmissionContext(
            ['du_course_uid' => 'Kelas Leader', 'bundle__du_course_uid__0' => 'Kelas Basi'],
            $this->duForm(),
            User::factory()->create(),
            $this->fields(),
            FormSubmissionMode::Bundle,
            2,
        );

        try {
            app(FormSubmissionOrchestrator::class)->validateAndMap($context);
            self::fail('Kelas anggota yang tidak ada di cache harus ditolak.');
        } catch (ValidationException $exception) {
            self::assertSame(['bundle__du_course_uid__0' => [__('Kelas DU yang dipilih tidak tersedia.')]], $exception->errors());
        }

        Http::assertNothingSent();
    }

    public function test_three_slot_bundle_resolves_courses_with_constant_cache_queries(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Kelas B', 'is_active' => true]);
        Http::fake();

        $payload = null;
        $threeSlots = $this->countCourseQueries(function () use (&$payload): void {
            $payload = $this->submitBundleCourses(
                ['Kelas A', 'course-b', 'Kelas A', 'course-b'],
                ['member-1@example.test', 'member-2@example.test', 'member-3@example.test'],
            );
        });
        $twoSlots = $this->countCourseQueries(fn (): FormSubmissionPayload => $this->submitBundleCourses(
            ['Kelas A', 'course-b'],
            ['member-4@example.test'],
        ));

        self::assertSame($twoSlots, $threeSlots, 'Jumlah query cache tidak boleh bertambah per slot.');
        self::assertInstanceOf(FormSubmissionPayload::class, $payload);
        self::assertSame('Kelas A', $payload->answers['leader']['du_course_uid']);
        self::assertSame('course-b', $payload->answers['members'][0]['du_course_uid']);
        self::assertSame('Kelas A', $payload->answers['members'][1]['du_course_uid']);
        self::assertSame('course-b', $payload->answers['members'][2]['du_course_uid']);
        Http::assertNothingSent();
    }

    public function test_selected_course_uids_reject_an_active_course_outside_the_selection(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Kelas B', 'is_active' => true]);
        Http::fake();
        [$form, $fields] = $this->formWithSelection(['course-a']);

        $context = new FormSubmissionContext(
            ['du_course_uid' => 'course-b'],
            $form,
            User::factory()->create(),
            $fields,
            FormSubmissionMode::Single,
            1,
        );

        try {
            app(FormSubmissionOrchestrator::class)->validateAndMap($context);
            self::fail('Course aktif di luar selected_course_uids harus ditolak.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('du_course_uid', $exception->errors());
        }

        Http::assertNothingSent();
    }

    public function test_selected_course_uids_accept_a_selected_course(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Kelas B', 'is_active' => true]);
        Http::fake();
        [$form, $fields] = $this->formWithSelection(['course-a']);

        $payload = app(FormSubmissionOrchestrator::class)->validateAndMap(new FormSubmissionContext(
            ['du_course_uid' => 'course-a'],
            $form,
            User::factory()->create(),
            $fields,
            FormSubmissionMode::Single,
            1,
        ));

        self::assertSame('course-a', $payload->answers['leader']['du_course_uid']);
        Http::assertNothingSent();
    }

    /**
     * @param  list<string>  $selectedUids
     * @return array{0: Form, 1: Collection<int, FormField>}
     */
    private function formWithSelection(array $selectedUids): array
    {
        $form = Form::factory()->create(['metadata' => ['purpose' => FormPurpose::DKelas->value]]);
        $field = FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'du_course_uid',
            'label' => 'Kelas',
            'input_type' => 'selectInput',
            'order' => 0,
            'metadata' => [
                'system' => true,
                'locked' => true,
                'options_source' => 'du_catalog',
                'is_multiple' => false,
                'selected_course_uids' => $selectedUids,
                'rules' => ['required' => true],
            ],
        ]);

        return [$form, new Collection([$field])];
    }

    /**
     * @param  list<string>  $courses  leader value first, then one per member slot
     * @param  list<string>  $memberEmails
     */
    private function submitBundleCourses(array $courses, array $memberEmails): FormSubmissionPayload
    {
        $input = ['du_course_uid' => $courses[0], 'team_member_emails' => $memberEmails];
        foreach (array_slice($courses, 1) as $index => $value) {
            $input['bundle__du_course_uid__'.$index] = $value;
        }

        return app(FormSubmissionOrchestrator::class)->validateAndMap(new FormSubmissionContext(
            $input,
            $this->duForm(),
            User::factory()->create(),
            $this->fields(),
            FormSubmissionMode::Bundle,
            count($courses),
        ));
    }

    /** @return int queries touching du_courses while the callback runs */
    private function countCourseQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function (QueryExecuted $query) use (&$count): void {
            if (str_contains($query->sql, 'du_courses')) {
                $count++;
            }
        });

        $callback();

        return $count;
    }

    public function test_missing_cached_course_rejects_submission(): void
    {
        Http::fake();
        $context = new FormSubmissionContext(
            ['du_course_uid' => 'course-test'],
            $this->duForm(),
            User::factory()->create(),
            $this->fields(),
            FormSubmissionMode::Single,
            1,
        );

        $this->expectException(ValidationException::class);

        app(FormSubmissionOrchestrator::class)->validateAndMap($context);
    }

    public function test_orchestrator_does_not_fetch_catalog_for_non_du_form(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $form = new Form(['metadata' => ['purpose' => FormPurpose::Registration->value]]);
        $context = new FormSubmissionContext(['name' => 'Ada'], $form, $user, new Collection([
            new FormField(['name' => 'name', 'input_type' => 'input', 'metadata' => ['rules' => ['required' => true]]]),
        ]), FormSubmissionMode::Single, 1);

        app(FormSubmissionOrchestrator::class)->validateAndMap($context);
        Http::assertNothingSent();
    }

    public function test_non_du_form_keeps_original_validation(): void
    {
        $fields = new Collection([
            new FormField(['name' => 'name', 'input_type' => 'input', 'metadata' => ['rules' => ['required' => true]]]),
        ]);
        $validated = FormSubmissionRules::validate(['name' => 'Ada'], $fields, FormSubmissionMode::Single, 1);
        self::assertSame(['name' => 'Ada'], $validated);
    }
}

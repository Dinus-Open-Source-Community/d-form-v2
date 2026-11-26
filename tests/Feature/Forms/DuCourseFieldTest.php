<?php

namespace Tests\Feature\Forms;

use App\Models\DuCourse;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\Du\DuCourseFieldService;
use App\Services\Form\PublicFormFillPayload;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DuCourseFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_ensure_creates_the_kelas_field_with_required_rules(): void
    {
        $form = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']]);

        $field = app(DuCourseFieldService::class)->ensure($form);

        $this->assertSame('Kelas', $field->label);
        $this->assertSame('du_course_uid', $field->name);
        $this->assertSame('selectInput', $field->input_type);
        $this->assertTrue($field->metadata['locked']);
        $this->assertTrue($field->metadata['system']);
        $this->assertTrue($field->metadata['rules']['required']);
        $this->assertSame('du_catalog', $field->metadata['options_source']);
        $this->assertTrue($field->metadata['duplicatable']);
    }

    public function test_ensure_is_idempotent_and_never_duplicates_the_field(): void
    {
        $form = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']]);
        $service = app(DuCourseFieldService::class);

        $first = $service->ensure($form);
        $second = $service->ensure($form);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $form->formFields()->where('name', 'du_course_uid')->count());
        $this->assertSame('selectInput', $second->input_type);
        $this->assertTrue($second->metadata['rules']['required']);
        $this->assertTrue($second->metadata['system']);
        $this->assertTrue($second->metadata['locked']);
        $this->assertSame('du_catalog', $second->metadata['options_source']);
        $this->assertTrue($second->metadata['duplicatable']);
    }

    public function test_ensure_marks_the_kelas_field_appendable_only_for_team_mode(): void
    {
        $service = app(DuCourseFieldService::class);
        $single = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single']]);
        $bundle = Form::factory()->create([
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'bundle', 'max_team_size' => 3],
        ]);
        $team = Form::factory()->create([
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'team', 'team_size' => 2],
        ]);
        // Baris lama dibuat saat aturan masih `is_append = false`; ensure() harus memperbaikinya.
        $team->formFields()->create([
            'input_type' => 'selectInput',
            'label' => 'Kelas',
            'name' => 'du_course_uid',
            'metadata' => [],
            'order' => 0,
            'is_append' => false,
        ]);

        $this->assertFalse($service->ensure($single)->is_append);
        $this->assertFalse($service->ensure($bundle)->is_append);
        $this->assertTrue($service->ensure($team)->is_append);
    }

    public function test_ensure_restores_a_soft_deleted_field(): void
    {
        $form = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']]);
        $service = app(DuCourseFieldService::class);
        $field = $service->ensure($form);
        $answer = $form->formAnswers()->create(['answers' => ['du_course_uid' => 'course-a'], 'is_active' => true]);
        $originalAnswers = $answer->answers;
        $field->delete();

        $restored = $service->ensure($form);

        $this->assertSame($field->id, $restored->id);
        $this->assertNull($restored->deleted_at);
        $answer->refresh();
        $this->assertSame($originalAnswers, $answer->answers);
    }

    public function test_ensure_preserves_an_existing_description(): void
    {
        $form = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']]);
        $field = app(DuCourseFieldService::class)->ensure($form);
        $field->forceFill(['description' => 'Pilih kelas yang kamu ikuti'])->save();

        $again = app(DuCourseFieldService::class)->ensure($form);

        $this->assertSame('Pilih kelas yang kamu ikuti', $again->description);
    }

    public function test_ensure_preserves_edited_label_and_does_not_write_when_unchanged(): void
    {
        $form = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']]);
        $service = app(DuCourseFieldService::class);
        $service->ensure($form);
        $field = $form->formFields()->where('name', 'du_course_uid')->firstOrFail();
        $field->forceFill(['label' => 'Kelas Pilihan'])->save();
        $timestamp = $field->fresh()->updated_at;

        Carbon::setTestNow($timestamp->copy()->addHour());
        $again = $service->ensure($form);
        Carbon::setTestNow();

        $this->assertSame('Kelas Pilihan', $again->label);
        $this->assertSame($timestamp->toDateTimeString(), $again->updated_at->toDateTimeString());
    }

    public function test_ensure_repairs_metadata_without_destroying_existing_keys(): void
    {
        $form = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']]);
        $field = $form->formFields()->create([
            'input_type' => 'input', 'label' => 'Lama', 'name' => 'du_course_uid',
            'metadata' => ['custom' => 'keep-me', 'rules' => ['min' => 2]], 'order' => 3, 'is_append' => true,
        ]);

        $repaired = app(DuCourseFieldService::class)->ensure($form);

        $this->assertSame('keep-me', $repaired->metadata['custom']);
        $this->assertSame(2, $repaired->metadata['rules']['min']);
        $this->assertTrue($repaired->metadata['rules']['required']);
        $this->assertTrue($repaired->metadata['duplicatable']);
        $this->assertSame('Lama', $repaired->label);
        $this->assertSame('selectInput', $repaired->input_type);
        $this->assertFalse($repaired->is_append);
        $this->assertSame($field->id, $repaired->id);
    }

    public function test_ensure_repairs_existing_field_without_touching_answers(): void
    {
        $form = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']]);
        $field = $form->formFields()->create(['input_type' => 'input', 'label' => 'Old', 'name' => 'du_course_uid', 'metadata' => [], 'order' => 4, 'is_append' => false]);
        $answer = $form->formAnswers()->create(['answers' => ['du_course_uid' => 'course-old'], 'is_active' => true]);
        $originalAnswers = $answer->answers;

        $repaired = app(DuCourseFieldService::class)->ensure($form);

        $this->assertSame($field->id, $repaired->id);
        $answer->refresh();
        $this->assertSame($originalAnswers, $answer->answers);
        $this->assertSame(1, $form->formAnswers()->count());
        $this->assertSame(1, $form->formFields()->where('name', 'du_course_uid')->count());
    }

    public function test_apply_options_reads_cache_and_never_calls_du(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Kelas B', 'is_active' => false]);
        Http::fake();

        $field = app(DuCourseFieldService::class)->applyOptions([
            'name' => 'du_course_uid',
            'metadata' => ['options_source' => 'du_catalog'],
        ]);

        $this->assertSame([['type' => 'text', 'label' => 'Kelas A', 'value' => 'course-a']], $field['metadata']['optionChoices']);
        Http::assertNothingSent();
    }

    public function test_apply_options_returns_non_du_fields_unchanged(): void
    {
        Http::fake();
        $service = app(DuCourseFieldService::class);
        $plainField = ['name' => 'name', 'metadata' => ['rules' => ['required' => true]]];
        $noSourceField = ['name' => 'du_course_uid', 'metadata' => ['label' => 'Kelas']];

        $this->assertSame($plainField, $service->applyOptions($plainField));
        $this->assertSame($noSourceField, $service->applyOptions($noSourceField));
        Http::assertNothingSent();
    }

    public function test_public_fill_payload_injects_cached_options(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        Http::fake();
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id, 'metadata' => ['purpose' => 'd_kelas']]);
        app(DuCourseFieldService::class)->ensure($form);

        $fields = PublicFormFillPayload::build($event, $form)['fields'];
        $courseField = collect($fields)->firstWhere('name', 'du_course_uid');

        $this->assertSame('Kelas A', $courseField['metadata']['optionChoices'][0]['label']);
        $this->assertSame('course-a', $courseField['metadata']['optionChoices'][0]['value']);
        $this->assertTrue($courseField['metadata']['duplicatable']);
        Http::assertNothingSent();
    }

    /**
     * Label/description edit pada field sistem harus lolos jalur nyata builder:
     * autosave snapshot (`POST /fields`) lalu settings/publish (`PUT /forms/{form}`).
     */
    public function test_label_and_description_edits_survive_field_autosave_and_settings_update(): void
    {
        [$admin, $event, $form, $field] = $this->dKelasFormWithSystemField();
        $row = $this->systemFieldRow($field, 'Kelas Pilihan', 'Pilih kelas yang kamu ikuti');

        $this->actingAs($admin)
            ->postJson(route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form], false), [
                'fields' => [$row],
            ])
            ->assertOk();

        $field->refresh();
        $this->assertSame('Kelas Pilihan', $field->label);
        $this->assertSame('Pilih kelas yang kamu ikuti', $field->description);

        $this->actingAs($admin)
            ->put(
                route('dashboard.events.forms.update', ['event' => $event, 'form' => $form], false),
                $this->settingsPayload($form, [$row]),
            )
            ->assertRedirect(route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]))
            ->assertSessionHasNoErrors();

        $field->refresh();
        $this->assertSame('Kelas Pilihan', $field->label);
        $this->assertSame('Pilih kelas yang kamu ikuti', $field->description);
        $this->assertSame(1, $form->formFields()->where('name', 'du_course_uid')->count());
    }

    /**
     * Manual save/publish sebelum debounce fields: hanya settings PUT yang membawa
     * label/description baru. Nilai itu harus tetap tersimpan pada field sistem.
     */
    public function test_settings_update_applies_label_and_description_edits_to_the_system_field(): void
    {
        [$admin, $event, $form, $field] = $this->dKelasFormWithSystemField();
        $row = $this->systemFieldRow($field, 'Kelas Pilihan', 'Pilih kelas yang kamu ikuti');

        $this->actingAs($admin)
            ->put(
                route('dashboard.events.forms.update', ['event' => $event, 'form' => $form], false),
                $this->settingsPayload($form, [$row]),
            )
            ->assertRedirect(route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]))
            ->assertSessionHasNoErrors();

        $field->refresh();
        $this->assertSame('Kelas Pilihan', $field->label);
        $this->assertSame('Pilih kelas yang kamu ikuti', $field->description);
        $this->assertSame('selectInput', $field->input_type);
        $this->assertTrue($field->metadata['locked']);
        $this->assertTrue($field->metadata['system']);
        $this->assertSame('du_catalog', $field->metadata['options_source']);
        $this->assertTrue($field->metadata['duplicatable']);
        $this->assertSame(1, $form->formFields()->where('name', 'du_course_uid')->count());
    }

    /**
     * Builder tidak boleh merusak identitas field sistem lewat snapshot: rename, ubah tipe,
     * dan metadata yang di-strip semuanya dikembalikan ke bentuk sistem; label tetap tersimpan.
     */
    public function test_field_autosave_forces_a_renamed_system_row_back_to_one_valid_system_field(): void
    {
        [$admin, $event, $form, $field] = $this->dKelasFormWithSystemField();

        $this->actingAs($admin)
            ->postJson(route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form], false), [
                'fields' => [[
                    'id' => $field->id,
                    'label' => 'Kelas Pilihan',
                    'description' => null,
                    'type' => 'textarea',
                    'name' => 'kelas_pilihan',
                    'order' => 0,
                    'metadata' => ['rules' => ['required' => false]],
                ]],
            ])
            ->assertOk();

        $field->refresh();

        $this->assertSame('du_course_uid', $field->name);
        $this->assertSame('selectInput', $field->input_type);
        $this->assertSame('Kelas Pilihan', $field->label);
        $this->assertTrue($field->metadata['system']);
        $this->assertTrue($field->metadata['locked']);
        $this->assertTrue($field->metadata['duplicatable']);
        $this->assertSame('du_catalog', $field->metadata['options_source']);
        $this->assertTrue($field->metadata['rules']['required']);
        $this->assertFalse($field->metadata->has('optionChoices'));
        $this->assertSame(1, $form->formFields()->count());
        $this->assertSame(0, $form->formFields()->where('name', 'kelas_pilihan')->count());
    }

    /**
     * @return array{0: User, 1: Event, 2: Form, 3: FormField}
     */
    private function dKelasFormWithSystemField(): array
    {
        Http::fake();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $form = Form::factory()->create(['event_id' => $event->id, 'metadata' => ['purpose' => 'd_kelas']]);
        app(DuCourseFieldService::class)->ensure($form);
        $field = $form->formFields()->where('name', 'du_course_uid')->firstOrFail();

        return [$admin, $event, $form, $field];
    }

    /**
     * @return array<string, mixed>
     */
    private function systemFieldRow(FormField $field, string $label, ?string $description): array
    {
        return [
            'id' => $field->id,
            'label' => $label,
            'description' => $description,
            'type' => 'select',
            'name' => 'du_course_uid',
            'order' => $field->order,
            'metadata' => [
                'is_multiple' => false,
                'rules' => ['required' => true],
                'builderType' => 'dropdown',
                'locked' => true,
                'system' => true,
                'options_source' => 'du_catalog',
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function settingsPayload(Form $form, array $fields): array
    {
        return [
            'title' => $form->title,
            'description' => $form->description,
            'closed_at' => $form->closed_at?->format('Y-m-d H:i:s') ?? now()->addDays(10)->format('Y-m-d H:i:s'),
            'visible_for' => ['public'],
            'banner_url' => null,
            'banner_caption' => null,
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single'],
            'fields' => $fields,
        ];
    }
}

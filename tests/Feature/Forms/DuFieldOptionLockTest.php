<?php

namespace Tests\Feature\Forms;

use App\Models\DuCourse;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\Du\DuCourseFieldService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DuFieldOptionLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_fields_snapshot_cannot_change_option_content_while_du_is_enabled(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();
        $normal = $this->normalSelectField($form);

        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), [
                'fields' => [
                    $this->flaggedRow($field, [
                        'label' => 'Kelas Baru',
                        'description' => 'Deskripsi baru',
                        'name' => 'class_renamed',
                        'order' => 3,
                        'optionLabels' => ['Changed', 'Other'],
                    ]),
                    $this->normalRow($normal, ['C', 'D']),
                ],
            ])
            ->assertOk();

        $field->refresh();
        $this->assertSame('class', $field->name);
        $this->assertSame('selectInput', $field->input_type);
        $this->assertSame('Kelas Baru', $field->label);
        $this->assertSame('Deskripsi baru', $field->description);
        $this->assertSame(['AI Engineer', 'DevOps'], $this->optionLabels($field));
        $this->assertSame('AI Engineer,DevOps', $field->metadata['rules']['in']);
        $this->assertSame('AI Engineer,DevOps', $field->metadata['options']);
        $this->assertSame('course-ai', $field->metadata['du_option_map']['AI Engineer']['course_uid']);

        // Field tanpa flag tetap bisa diedit bebas: lock tidak bocor ke field lain.
        $normal->refresh();
        $this->assertSame(['C', 'D'], $this->optionLabels($normal));
        $this->assertSame('C,D', $normal->metadata['rules']['in']);
    }

    public function test_settings_update_cannot_change_option_content_while_du_is_enabled(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();

        $this->actingAs($admin)
            ->put($this->updateUrl($event, $form), $this->settingsPayload($form, [
                $this->flaggedRow($field, [
                    'label' => 'Kelas Baru',
                    'name' => 'class_renamed',
                    'optionLabels' => ['Changed', 'Other'],
                ]),
            ]))
            ->assertRedirect(route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]))
            ->assertSessionHasNoErrors();

        $field->refresh();
        $this->assertSame('class', $field->name);
        $this->assertSame('selectInput', $field->input_type);
        $this->assertSame('Kelas Baru', $field->label);
        $this->assertSame(['AI Engineer', 'DevOps'], $this->optionLabels($field));
        $this->assertSame('AI Engineer,DevOps', $field->metadata['rules']['in']);
    }

    public function test_label_change_is_allowed_and_the_mapping_survives_both_writers(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();

        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), [
                'fields' => [$this->flaggedRow($field, ['label' => 'Kelas'])],
            ])
            ->assertOk();

        $field->refresh();
        $this->assertSame('Kelas', $field->label);
        $this->assertSame(['AI Engineer', 'DevOps'], $this->optionLabels($field));
        $this->assertSame('course-ai', $field->metadata['du_option_map']['AI Engineer']['course_uid']);

        $this->actingAs($admin)
            ->put($this->updateUrl($event, $form), $this->settingsPayload($form, [
                $this->flaggedRow($field, [
                    'label' => 'Kelas Peserta',
                    'mapping' => [
                        'AI Engineer' => ['course_uid' => 'course-ai', 'course_title' => 'AI Engineering'],
                        'DevOps' => ['course_uid' => 'course-devops', 'course_title' => 'DevOps Engineering'],
                    ],
                ]),
            ]))
            ->assertRedirect(route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]))
            ->assertSessionHasNoErrors();

        $field->refresh();
        $this->assertSame('Kelas Peserta', $field->label);
        $this->assertSame(['AI Engineer', 'DevOps'], $this->optionLabels($field));
        $this->assertSame('course-ai', $field->metadata['du_option_map']['AI Engineer']['course_uid']);
        $this->assertSame('course-devops', $field->metadata['du_option_map']['DevOps']['course_uid']);
    }

    public function test_disable_save_keeps_identity_but_allows_option_edits(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();
        $row = $this->flaggedRow($field, [
            'enabled' => false,
            'name' => 'class_renamed',
            'optionLabels' => ['Changed', 'Other'],
            'isMultiple' => true,
        ]);
        $row['type'] = 'radio';
        unset($row['metadata']['rules']['multiple']);

        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), ['fields' => [$row]])
            ->assertOk();

        $field->refresh();
        $this->assertFalse($field->metadata['du_redeem_enabled']);
        $this->assertSame('class', $field->name);
        $this->assertSame('selectInput', $field->input_type);
        $this->assertFalse($field->metadata['is_multiple']);
        $this->assertArrayNotHasKey('multiple', $field->metadata['rules']);
        $this->assertSame(['Changed', 'Other'], $this->optionLabels($field));
    }

    public function test_a_flagged_custom_field_blocks_switching_the_purpose_to_d_kelas(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();
        $dKelasMetadata = ['purpose' => 'd_kelas', 'registration_mode' => 'single'];

        $this->actingAs($admin)
            ->putJson($this->updateUrl($event, $form), $this->settingsPayload($form, [
                $this->flaggedRow($field, []),
            ], $dKelasMetadata))
            ->assertStatus(422)
            ->assertInvalid([
                'metadata.purpose' => 'Matikan integrasi DU pada field kustom sebelum mengubah tujuan form menjadi D-Kelas.',
            ]);

        $this->assertSame('registration', $form->fresh()->purpose()->value);

        // Flag off dulu (alur rollback terdokumentasi): konversi tujuan baru diizinkan.
        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), [
                'fields' => [$this->flaggedRow($field, ['enabled' => false])],
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->put($this->updateUrl($event, $form), $this->settingsPayload($form, [
                $this->flaggedRow($field, ['enabled' => false]),
            ], $dKelasMetadata))
            ->assertRedirect(route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]))
            ->assertSessionHasNoErrors();

        $this->assertSame('d_kelas', $form->fresh()->purpose()->value);
    }

    public function test_numeric_flag_is_treated_as_enabled_for_the_duplicate_guard(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();
        $second = $this->normalSelectField($form);
        $secondRow = $this->flaggedRow($second, []);
        $secondRow['metadata']['du_redeem_enabled'] = 1;

        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), [
                'fields' => [$this->flaggedRow($field, []), $secondRow],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.0', 'Hanya satu field kelas DU yang boleh aktif.');
    }

    public function test_numeric_flag_is_treated_as_enabled_by_the_lock(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();
        $row = $this->flaggedRow($field, ['optionLabels' => ['Hijacked']]);
        $row['metadata']['du_redeem_enabled'] = 1;

        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), ['fields' => [$row]])
            ->assertOk();

        $field->refresh();
        $this->assertTrue($field->metadata['du_redeem_enabled']);
        $this->assertSame(['AI Engineer', 'DevOps'], $this->optionLabels($field));
    }

    public function test_snapshot_cannot_flip_a_locked_class_field_to_multiple(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();

        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), [
                'fields' => [$this->flaggedRow($field, ['isMultiple' => true])],
            ])
            ->assertOk();

        $field->refresh();
        $this->assertFalse($field->metadata['is_multiple']);
        $this->assertArrayNotHasKey('multiple', $field->metadata['rules']);
        $this->assertSame(['AI Engineer', 'DevOps'], $this->optionLabels($field));
    }

    public function test_disabling_du_allows_option_edits_and_the_lock_resumes_after_enabling(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();

        // Disable: the documented flow to edit the fixed options.
        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), [
                'fields' => [$this->flaggedRow($field, [
                    'enabled' => false,
                    'optionLabels' => ['Changed', 'Other'],
                ])],
            ])
            ->assertOk();

        $field->refresh();
        $this->assertFalse($field->metadata['du_redeem_enabled']);
        $this->assertSame(['Changed', 'Other'], $this->optionLabels($field));

        // Enable again with the edited options.
        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), [
                'fields' => [$this->flaggedRow($field, ['optionLabels' => ['Changed', 'Other']])],
            ])
            ->assertOk();

        // The lock resumes on the next save while the flag stays true.
        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), [
                'fields' => [$this->flaggedRow($field, ['optionLabels' => ['Hijacked']])],
            ])
            ->assertOk();

        $field->refresh();
        $this->assertTrue($field->metadata['du_redeem_enabled']);
        $this->assertSame(['Changed', 'Other'], $this->optionLabels($field));
    }

    public function test_snapshot_cannot_delete_the_locked_field(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();

        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), ['fields' => []])
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.0', 'Field kelas DU tidak dapat dihapus.');

        $this->assertSame(1, $form->formFields()->count());
        $this->assertDatabaseHas('form_fields', ['id' => $field->id, 'deleted_at' => null]);
    }

    public function test_snapshot_cannot_enable_du_on_a_second_field(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();
        $second = $this->normalSelectField($form);

        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), [
                'fields' => [
                    $this->flaggedRow($field, []),
                    $this->flaggedRow($second, []),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.0', 'Hanya satu field kelas DU yang boleh aktif.');
    }

    public function test_recreated_field_with_a_new_id_still_gets_the_stored_options(): void
    {
        [, , $form, $field] = $this->formWithFlaggedField();
        $newId = (string) Str::uuid();
        $row = array_replace(
            $this->flaggedRow($field, ['name' => 'class', 'optionLabels' => ['Hijacked']]),
            ['id' => $newId],
        );

        $rows = app(DuCourseFieldService::class)->enforceLockedOptionContent($form, [$row]);
        $locked = $rows[0];

        $this->assertSame($newId, $locked['id']);
        $this->assertSame('class', $locked['name']);
        $this->assertSame(
            ['AI Engineer', 'DevOps'],
            array_map(
                static fn (array $choice): string => (string) $choice['label'],
                $locked['metadata']['optionChoices'],
            ),
        );
        $this->assertSame('course-ai', $locked['metadata']['du_option_map']['AI Engineer']['course_uid']);
    }

    public function test_autosave_drops_unknown_options_and_missing_courses_from_the_mapping(): void
    {
        [$admin, $event, $form, $field] = $this->formWithFlaggedField();

        $this->actingAs($admin)
            ->postJson($this->fieldsUrl($event, $form), [
                'fields' => [$this->flaggedRow($field, [
                    'mapping' => [
                        'AI Engineer' => ['course_uid' => 'course-ai', 'course_title' => 'AI Engineering'],
                        'DevOps' => ['course_uid' => 'course-missing', 'course_title' => 'Missing'],
                        'Nope' => ['course_uid' => 'course-ai', 'course_title' => 'AI Engineering'],
                    ],
                ])],
            ])
            ->assertOk();

        $mapping = $field->refresh()->metadata['du_option_map'];

        $this->assertSame(['AI Engineer'], array_keys($mapping));
        $this->assertSame('course-ai', $mapping['AI Engineer']['course_uid']);
        $this->assertSame('AI Engineering', $mapping['AI Engineer']['course_title']);
    }

    public function test_create_rejects_more_than_one_flagged_field(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.events.forms.store', ['event' => $event], false), $this->createPayload([
                $this->createRow('class', 'Class', 'select', 1),
                $this->createRow('class_second', 'Class Kedua', 'select', 2),
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.0', 'Hanya satu field kelas DU yang boleh aktif.');

        $this->assertSame(0, Form::query()->where('event_id', $event->id)->count());
    }

    public function test_create_persists_a_single_flagged_field(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.events.forms.store', ['event' => $event], false), $this->createPayload([
                $this->createRow('class', 'Class', 'select', 1),
            ]))
            ->assertRedirect();

        $form = Form::query()->where('event_id', $event->id)->firstOrFail();
        $field = $form->formFields()->where('name', 'class')->firstOrFail();

        $this->assertTrue($field->metadata['du_redeem_enabled']);
        $this->assertSame(['AI Engineer', 'DevOps'], $this->optionLabels($field));
        $this->assertSame('AI Engineer,DevOps', $field->metadata['rules']['in']);
    }

    public function test_create_rejects_flagging_a_non_choice_field(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $row = $this->createRow('full_name', 'Nama', 'input', 1);
        $row['metadata'] = ['du_redeem_enabled' => true, 'type' => 'text', 'rules' => []];

        $this->actingAs($admin)
            ->postJson(route('dashboard.events.forms.store', ['event' => $event], false), $this->createPayload([$row]))
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.0', 'Redeem code DU hanya untuk field pilihan (select atau radio).');

        $this->assertSame(0, Form::query()->where('event_id', $event->id)->count());
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function createPayload(array $fields): array
    {
        return [
            'title' => 'Form DU baru',
            'description' => 'Deskripsi',
            'closed_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'visible_for' => ['public'],
            'metadata' => ['purpose' => 'registration', 'registration_mode' => 'single'],
            'fields' => $fields,
        ];
    }

    /** @return array<string, mixed> */
    private function createRow(string $name, string $label, string $type, int $order): array
    {
        $metadata = [
            'du_redeem_enabled' => true,
            'optionChoices' => [
                ['id' => 'o1', 'type' => 'text', 'label' => 'AI Engineer'],
                ['id' => 'o2', 'type' => 'text', 'label' => 'DevOps'],
            ],
            'rules' => ['required' => true, 'in' => 'AI Engineer,DevOps'],
        ];

        if ($type === 'select') {
            $metadata['is_multiple'] = false;
        }

        return [
            'label' => $label,
            'description' => null,
            'type' => $type,
            'name' => $name,
            'order' => $order,
            'metadata' => $metadata,
        ];
    }

    private function formWithFlaggedField(): array
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);
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
                'is_multiple' => false,
                'optionChoices' => [
                    ['id' => 'o1', 'type' => 'text', 'label' => 'AI Engineer'],
                    ['id' => 'o2', 'type' => 'text', 'label' => 'DevOps'],
                ],
                'rules' => ['required' => true, 'in' => 'AI Engineer,DevOps'],
                'options' => 'AI Engineer,DevOps',
                'du_option_map' => [
                    'AI Engineer' => ['course_uid' => 'course-ai', 'course_title' => 'AI Engineering'],
                ],
            ],
        ]);

        // Mapping normalization only keeps uids that exist in the cache, any active state.
        DuCourse::factory()->create(['uid' => 'course-ai', 'title' => 'AI Engineering', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-devops', 'title' => 'DevOps Engineering', 'is_active' => false]);

        return [$admin, $event, $form, $field];
    }

    private function normalSelectField(Form $form): FormField
    {
        return FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'major',
            'label' => 'Jurusan',
            'input_type' => 'selectInput',
            'order' => 2,
            'metadata' => [
                'is_multiple' => false,
                'optionChoices' => [
                    ['id' => 'm1', 'type' => 'text', 'label' => 'A'],
                    ['id' => 'm2', 'type' => 'text', 'label' => 'B'],
                ],
                'rules' => ['required' => false, 'in' => 'A,B'],
            ],
        ]);
    }

    /**
     * @param  array{label?: string, description?: string|null, name?: string, order?: int, optionLabels?: list<string>, mapping?: array<string, array{course_uid: string, course_title: string}>, enabled?: bool, isMultiple?: bool}  $overrides
     * @return array<string, mixed>
     */
    private function flaggedRow(FormField $field, array $overrides): array
    {
        $labels = $overrides['optionLabels'] ?? ['AI Engineer', 'DevOps'];
        $csv = implode(',', $labels);
        $isMultiple = $overrides['isMultiple'] ?? false;

        return [
            'id' => $field->id,
            'label' => $overrides['label'] ?? $field->label,
            'description' => $overrides['description'] ?? $field->description,
            'type' => 'select',
            'name' => $overrides['name'] ?? $field->name,
            'order' => $overrides['order'] ?? $field->order,
            'metadata' => [
                'du_redeem_enabled' => $overrides['enabled'] ?? true,
                'is_multiple' => $isMultiple,
                'optionChoices' => array_map(
                    static fn (string $label, int $index): array => [
                        'id' => 'o'.($index + 1),
                        'type' => 'text',
                        'label' => $label,
                    ],
                    $labels,
                    array_keys($labels),
                ),
                'rules' => array_filter([
                    'required' => true,
                    'in' => $csv,
                    'multiple' => $isMultiple ? true : null,
                ], static fn (mixed $value): bool => $value !== null),
                'du_option_map' => $overrides['mapping'] ?? [
                    'AI Engineer' => ['course_uid' => 'course-ai', 'course_title' => 'AI Engineering'],
                ],
            ],
        ];
    }

    /**
     * @param  list<string>  $toLabels
     * @return array<string, mixed>
     */
    private function normalRow(FormField $field, array $toLabels): array
    {
        return [
            'id' => $field->id,
            'label' => $field->label,
            'description' => $field->description,
            'type' => 'select',
            'name' => $field->name,
            'order' => $field->order,
            'metadata' => [
                'is_multiple' => false,
                'optionChoices' => array_map(
                    static fn (string $label, int $index): array => [
                        'id' => 'm'.($index + 1),
                        'type' => 'text',
                        'label' => $label,
                    ],
                    $toLabels,
                    array_keys($toLabels),
                ),
                'rules' => ['required' => false, 'in' => implode(',', $toLabels)],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>|null  $metadata
     */
    private function settingsPayload(Form $form, array $fields, ?array $metadata = null): array
    {
        return [
            'title' => $form->title,
            'description' => $form->description,
            'closed_at' => $form->closed_at?->format('Y-m-d H:i:s') ?? now()->addDays(10)->format('Y-m-d H:i:s'),
            'visible_for' => ['public'],
            'banner_url' => null,
            'banner_caption' => null,
            'metadata' => $metadata ?? ['purpose' => 'registration', 'registration_mode' => 'single'],
            'fields' => $fields,
        ];
    }

    private function fieldsUrl(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form], false);
    }

    private function updateUrl(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.update', ['event' => $event, 'form' => $form], false);
    }

    /** @return list<string> */
    private function optionLabels(FormField $field): array
    {
        $choices = $field->metadata['optionChoices'];
        $choices = $choices instanceof \Illuminate\Support\Collection ? $choices->all() : (array) $choices;

        return array_values(array_map(
            static fn (array $choice): string => (string) $choice['label'],
            $choices,
        ));
    }
}

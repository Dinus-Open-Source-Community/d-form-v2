<?php

namespace Tests\Feature\Forms;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormControllerM3ComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function storeUri(Event $event): string
    {
        return route('dashboard.events.forms.store', ['event' => $event], false);
    }

    private function updateUri(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.update', ['event' => $event, 'form' => $form], false);
    }

    /**
     * @return array<string, mixed>
     */
    private function formShell(): array
    {
        return [
            'title' => 'Registration',
            'description' => 'Form description',
            'closed_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'visible_for' => ['public'],
            'banner_url' => null,
            'banner_caption' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inputField(string $id, string $name, int $order, bool $isAppend = false): array
    {
        return [
            'id' => $id,
            'label' => 'L',
            'type' => 'input',
            'name' => $name,
            'order' => $order,
            'metadata' => ['type' => 'text', 'rules' => []],
            'is_append' => $isAppend,
        ];
    }

    public function test_store_rejects_duplicate_field_names_in_one_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();

        $dup = fn (string $id, int $order) => $this->inputField($id, 'same', $order);

        $this->actingAs($admin)
            ->post($this->storeUri($event), array_merge($this->formShell(), [
                'metadata' => [
                    'purpose' => 'registration',
                    'registration_mode' => 'bundle',
                    'max_team_size' => 5,
                ],
                'fields' => [
                    $dup((string) Str::uuid(), 1),
                    $dup((string) Str::uuid(), 2),
                ],
            ]))
            ->assertInvalid(['fields']);
    }

    public function test_store_persists_metadata_is_append_and_duplicatable(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();

        $fid = (string) Str::uuid();
        $this->actingAs($admin)
            ->post($this->storeUri($event), array_merge($this->formShell(), [
                'metadata' => [
                    'purpose' => 'registration',
                    'registration_mode' => 'team',
                    'max_team_size' => 8,
                    'team_size' => 4,
                ],
                'fields' => [
                    array_merge($this->inputField($fid, 'note', 1, true), [
                        'metadata' => [
                            'type' => 'text',
                            'rules' => [],
                            'duplicatable' => true,
                        ],
                    ]),
                ],
            ]))
            ->assertRedirect();

        $form = Form::query()->where('event_id', $event->id)->first();
        $this->assertNotNull($form);
        $this->assertSame('registration', $form->metadata['purpose'] ?? null);
        $this->assertSame('team', $form->metadata['registration_mode'] ?? null);
        $this->assertSame(8, $form->metadata['max_team_size'] ?? null);
        $this->assertSame(4, $form->metadata['team_size'] ?? null);

        $field = FormField::query()->where('form_id', $form->id)->first();
        $this->assertTrue($field->is_append);
        $this->assertTrue((bool) ($field->metadata->get('duplicatable') ?? false));
    }

    public function test_update_rejects_name_collision_with_existing_row(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'existing',
            'input_type' => 'input',
            'label' => 'Old',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => []],
        ]);

        $this->actingAs($admin)
            ->put($this->updateUri($event, $form), [
                'title' => $form->title,
                'description' => $form->description,
                'closed_at' => $form->closed_at->format('Y-m-d H:i:s'),
                'visible_for' => $form->visible_for->map(fn ($e) => $e->value)->values()->all(),
                'banner_url' => $form->banner_url,
                'banner_caption' => $form->banner_caption,
                'metadata' => [
                    'purpose' => 'registration',
                    'registration_mode' => 'single',
                ],
                'fields' => [
                    $this->inputField((string) Str::uuid(), 'existing', 2),
                ],
            ])
            ->assertInvalid(['fields.0.name']);
    }

    public function test_update_round_trips_form_metadata(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'registration', 'registration_mode' => 'single'],
        ]);
        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'a',
            'input_type' => 'input',
            'label' => 'A',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => []],
        ]);

        $field = $form->formFields()->first();
        $this->assertNotNull($field);

        $this->actingAs($admin)
            ->put($this->updateUri($event, $form), [
                'title' => $form->title,
                'description' => $form->description,
                'closed_at' => $form->closed_at->format('Y-m-d H:i:s'),
                'visible_for' => $form->visible_for->map(fn ($e) => $e->value)->values()->all(),
                'banner_url' => $form->banner_url,
                'banner_caption' => $form->banner_caption,
                'metadata' => [
                    'purpose' => 'registration',
                    'registration_mode' => 'bundle',
                    'max_team_size' => 12,
                ],
                'fields' => [
                    [
                        'id' => $field->id,
                        'label' => $field->label,
                        'type' => 'input',
                        'name' => $field->name,
                        'order' => 1,
                        'metadata' => ['type' => 'text', 'rules' => []],
                        'is_append' => false,
                    ],
                ],
            ])
            ->assertRedirect();

        $form->refresh();
        $this->assertSame('registration', $form->metadata['purpose'] ?? null);
        $this->assertSame('bundle', $form->metadata['registration_mode'] ?? null);
        $this->assertSame(12, $form->metadata['max_team_size'] ?? null);
    }

    public function test_store_rejects_team_mode_on_other_purpose_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post($this->storeUri($event), array_merge($this->formShell(), [
                'metadata' => [
                    'purpose' => 'other',
                    'registration_mode' => 'team',
                    'team_size' => 3,
                ],
                'fields' => [
                    $this->inputField((string) Str::uuid(), 'note', 1),
                ],
            ]))
            ->assertInvalid(['metadata.registration_mode']);
    }
}

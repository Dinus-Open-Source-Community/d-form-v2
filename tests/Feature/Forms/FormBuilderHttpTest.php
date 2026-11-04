<?php

namespace Tests\Feature\Forms;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Http\Middleware\HandleInertiaRequests;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormBuilderHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_batch_save_fields_with_order(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        $f1 = [
            'id' => (string) Str::uuid(),
            'label' => 'Name',
            'type' => 'input',
            'name' => 'full_name',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => ['required' => true]],
        ];
        $f2 = [
            'id' => (string) Str::uuid(),
            'label' => 'Note',
            'type' => 'textarea',
            'name' => 'note',
            'order' => 2,
            'metadata' => ['placeholder' => '', 'rules' => []],
        ];

        $this->actingAs($admin)
            ->post($this->fieldSavePath($event, $form), [
                'fields' => [$f1, $f2],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $form->id,
            'name' => 'full_name',
            'input_type' => 'input',
            'order' => 1,
        ]);
        $this->assertDatabaseHas('form_fields', [
            'form_id' => $form->id,
            'name' => 'note',
            'input_type' => 'textarea',
        ]);
    }

    public function test_rejects_duplicate_field_names_in_same_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        $row = static fn (string $id) => [
            'id' => $id,
            'label' => 'A',
            'type' => 'input',
            'name' => 'dup',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => []],
        ];

        $this->actingAs($admin)
            ->post($this->fieldSavePath($event, $form), [
                'fields' => [
                    $row((string) Str::uuid()),
                    array_merge($row((string) Str::uuid()), ['order' => 2]),
                ],
            ])
            ->assertInvalid(['fields']);
    }

    public function test_rejects_conflicting_name_with_existing_row(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'taken',
            'input_type' => 'input',
            'label' => 'Old',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => []],
        ]);

        $this->actingAs($admin)
            ->post($this->fieldSavePath($event, $form), [
                'fields' => [
                    [
                        'id' => (string) Str::uuid(),
                        'label' => 'New',
                        'type' => 'input',
                        'name' => 'taken',
                        'order' => 2,
                        'metadata' => ['type' => 'text', 'rules' => []],
                    ],
                ],
            ])
            ->assertInvalid(['fields.0.name']);
    }

    public function test_member_cannot_save_fields(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        $this->actingAs($member)
            ->post($this->fieldSavePath($event, $form), [
                'fields' => [
                    [
                        'id' => (string) Str::uuid(),
                        'label' => 'A',
                        'type' => 'input',
                        'name' => 'a',
                        'order' => 1,
                        'metadata' => ['type' => 'text', 'rules' => []],
                    ],
                ],
            ])
            ->assertRedirect(route('dashboard'));
    }

    public function test_invalid_field_payload_returns_validation_error_without_writing(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        $this->actingAs($admin)
            ->postJson($this->fieldSavePath($event, $form), [
                'fields' => [[
                    'type' => 'input',
                    'name' => 'missing_label',
                    'order' => 1,
                    'metadata' => ['type' => 'text', 'rules' => []],
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fields.0.label']);

        $this->assertDatabaseCount('form_fields', 0);
    }

    public function test_unexpected_database_failure_returns_json_500_with_request_id(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $otherForm = Form::factory()->create(['event_id' => $event->id]);
        $existingField = FormField::factory()->create(['form_id' => $otherForm->id]);
        $requestId = (string) Str::uuid();

        Log::spy();

        $response = $this->actingAs($admin)
            ->postJson($this->fieldSavePath($event, $form), [
                'fields' => [[
                    'id' => $existingField->id,
                    'label' => 'Duplicate ID',
                    'type' => 'input',
                    'name' => 'duplicate_id',
                    'order' => 1,
                    'metadata' => ['type' => 'text', 'rules' => []],
                ]],
            ], ['X-Request-ID' => $requestId]);

        $response
            ->assertInternalServerError()
            ->assertJson([
                'ok' => false,
                'message' => 'Fields cannot be saved',
                'request_id' => $requestId,
            ])
            ->assertHeader('X-Request-ID', $requestId);

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'form_fields_save_failed'
                && ($context['request_id'] ?? null) === $requestId
                && isset($context['exception_class']),
        );

        $this->assertDatabaseCount('form_fields', 1);
        $this->assertDatabaseHas('form_fields', [
            'id' => $existingField->id,
            'form_id' => $existingField->form_id,
        ]);
    }

    public function test_unexpected_failure_returns_inertia_500_with_request_id(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $otherForm = Form::factory()->create(['event_id' => $event->id]);
        $existingField = FormField::factory()->create(['form_id' => $otherForm->id]);
        $requestId = (string) Str::uuid();

        $response = $this->actingAs($admin)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Request-ID' => $requestId,
            ])
            ->post($this->fieldSavePath($event, $form), [
                'fields' => [[
                    'id' => $existingField->id,
                    'label' => 'Duplicate ID',
                    'type' => 'input',
                    'name' => 'duplicate_id',
                    'order' => 1,
                    'metadata' => ['type' => 'text', 'rules' => []],
                ]],
            ]);

        $response
            ->assertStatus(500)
            ->assertHeader('X-Request-ID', $requestId)
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 500);
    }

    private function fieldSavePath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.fields', [
            'event' => $event,
            'form' => $form,
        ], false);
    }
}

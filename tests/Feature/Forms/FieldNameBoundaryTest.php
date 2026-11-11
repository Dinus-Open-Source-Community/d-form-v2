<?php

namespace Tests\Feature\Forms;

use App\Models\Event;
use App\Models\Form;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Field names are the key of `form_answers.answers` and of Laravel validation
 * rules, so names that collide with reserved keys or that Laravel reads as
 * paths/wildcards are rejected at the field-write boundary.
 */
class FieldNameBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function fieldSavePath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form], false);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $name): array
    {
        return [
            'fields' => [[
                'id'       => (string) Str::uuid(),
                'label'    => 'Boundary Field',
                'type'     => 'input',
                'name'     => $name,
                'order'    => 1,
                'metadata' => ['type' => 'text', 'rules' => []],
            ]],
        ];
    }

    public function test_reserved_dotted_and_wildcard_names_are_rejected_with_the_offending_name(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $form  = Form::factory()->create(['event_id' => $event->id]);

        foreach (['team_member_emails', 'full.name', 'full*name', 'bundle__full_name__0'] as $name) {
            $this->actingAs($admin)
                ->postJson($this->fieldSavePath($event, $form), $this->payload($name))
                ->assertUnprocessable()
                ->assertInvalid(['fields.0.name' => $name]);
        }

        $this->assertDatabaseCount('form_fields', 0);
    }

    public function test_benign_field_name_is_still_accepted(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $form  = Form::factory()->create(['event_id' => $event->id]);

        $this->actingAs($admin)
            ->post($this->fieldSavePath($event, $form), $this->payload('full_name'))
            ->assertRedirect();

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $form->id,
            'name'    => 'full_name',
        ]);
    }
}

<?php

namespace Tests\Feature\Forms;

use App\Models\DuCourse;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class DuFieldMappingEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    public function test_du_mapping_route_rejects_requests_after_the_configured_limit(): void
    {
        [$admin, $event, $form, $field] = $this->registrationFormWithFlaggedField();
        DuCourse::factory()->create(['uid' => 'course-ai', 'title' => 'AI Engineering', 'is_active' => true]);
        $url = $this->mappingUrl($event, $form);
        $payload = [
            'field_id' => $field->id,
            'option_value' => 'AI Engineer',
            'course_uid' => 'course-ai',
        ];

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->actingAs($admin)->putJson($url, $payload)->assertOk();
        }

        $this->actingAs($admin)
            ->putJson($url, $payload)
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_maps_an_existing_option_to_an_active_course(): void
    {
        [$admin, $event, $form, $field] = $this->registrationFormWithFlaggedField();
        DuCourse::factory()->create(['uid' => 'course-ai', 'title' => 'AI Engineering', 'is_active' => true]);

        $this->actingAs($admin)
            ->putJson($this->mappingUrl($event, $form), [
                'field_id' => $field->id,
                'option_value' => 'AI Engineer',
                'course_uid' => 'course-ai',
            ])
            ->assertOk()
            ->assertExactJson(['course_uid' => 'course-ai', 'course_title' => 'AI Engineering']);

        $field->refresh();
        $mapping = $field->metadata['du_option_map']['AI Engineer'];

        $this->assertSame('course-ai', $mapping['course_uid']);
        $this->assertSame('AI Engineering', $mapping['course_title']);
    }

    public function test_rejects_an_option_that_is_not_part_of_the_field(): void
    {
        [$admin, $event, $form, $field] = $this->registrationFormWithFlaggedField();
        DuCourse::factory()->create(['uid' => 'course-ai', 'title' => 'AI Engineering', 'is_active' => true]);

        $this->actingAs($admin)
            ->putJson($this->mappingUrl($event, $form), [
                'field_id' => $field->id,
                'option_value' => 'Nope',
                'course_uid' => 'course-ai',
            ])
            ->assertStatus(422);

        $this->assertArrayNotHasKey('du_option_map', $field->refresh()->metadata->all());
    }

    public function test_rejects_an_inactive_or_missing_course(): void
    {
        [$admin, $event, $form, $field] = $this->registrationFormWithFlaggedField();
        DuCourse::factory()->create(['uid' => 'course-off', 'title' => 'Old Course', 'is_active' => false]);

        $this->actingAs($admin)
            ->putJson($this->mappingUrl($event, $form), [
                'field_id' => $field->id,
                'option_value' => 'AI Engineer',
                'course_uid' => 'course-off',
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->putJson($this->mappingUrl($event, $form), [
                'field_id' => $field->id,
                'option_value' => 'AI Engineer',
                'course_uid' => 'course-missing',
            ])
            ->assertStatus(422);
    }

    public function test_rejects_a_field_that_does_not_belong_to_the_form_or_to_the_event(): void
    {
        [$admin, $event, $form, $field] = $this->registrationFormWithFlaggedField();
        DuCourse::factory()->create(['uid' => 'course-ai', 'title' => 'AI Engineering', 'is_active' => true]);

        $otherForm = Form::factory()->create(['event_id' => $event->id]);

        $this->actingAs($admin)
            ->putJson($this->mappingUrl($event, $otherForm), [
                'field_id' => $field->id,
                'option_value' => 'AI Engineer',
                'course_uid' => 'course-ai',
            ])
            ->assertNotFound();

        $otherEvent = Event::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->putJson($this->mappingUrl($otherEvent, $form), [
                'field_id' => $field->id,
                'option_value' => 'AI Engineer',
                'course_uid' => 'course-ai',
            ])
            ->assertNotFound();
    }

    public function test_rejects_a_field_without_the_du_flag(): void
    {
        [$admin, $event, $form, $field] = $this->registrationFormWithFlaggedField();
        $field->update(['metadata' => ['optionChoices' => $field->metadata['optionChoices']]]);
        DuCourse::factory()->create(['uid' => 'course-ai', 'title' => 'AI Engineering', 'is_active' => true]);

        $this->actingAs($admin)
            ->putJson($this->mappingUrl($event, $form), [
                'field_id' => $field->id,
                'option_value' => 'AI Engineer',
                'course_uid' => 'course-ai',
            ])
            ->assertStatus(422);
    }

    public function test_only_the_owning_admin_may_save_a_mapping(): void
    {
        [, $event, $form, $field] = $this->registrationFormWithFlaggedField();
        DuCourse::factory()->create(['uid' => 'course-ai', 'title' => 'AI Engineering', 'is_active' => true]);

        $otherAdmin = $this->admin();

        $this->actingAs($otherAdmin)
            ->putJson($this->mappingUrl($event, $form), [
                'field_id' => $field->id,
                'option_value' => 'AI Engineer',
                'course_uid' => 'course-ai',
            ])
            ->assertForbidden();

        $this->actingAs($otherAdmin)
            ->putJson($this->mappingUrl($event, $form), [])
            ->assertForbidden();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function mappingUrl(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.du-field-map', ['event' => $event, 'form' => $form], false);
    }

    /**
     * @return array{0: User, 1: Event, 2: Form, 3: FormField}
     */
    private function registrationFormWithFlaggedField(): array
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
            ],
        ]);

        return [$admin, $event, $form, $field];
    }
}

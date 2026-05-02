<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');
        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        return $user;
    }

    /** Event with registration window open right now. */
    private function openEvent(array $overrides = []): Event
    {
        return Event::factory()->create(array_merge([
            'registration_start' => now()->subDays(7),
            'registration_end'   => now()->addDays(30),
            'quota'              => 100,
            'registered_count'   => 0,
        ], $overrides));
    }

    /** Public form that is not closed. */
    private function openForm(Event $event, array $overrides = []): Form
    {
        return Form::factory()->create(array_merge([
            'event_id'    => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at'   => now()->addDays(30),
        ], $overrides));
    }

    /** Create a simple required text field on the given form. */
    private function textField(Form $form, string $name = 'full_name'): FormField
    {
        return FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => $name,
            'label'      => 'Full Name',
            'order'      => 1,
            'metadata'   => ['type' => 'text', 'rules' => ['required' => true]],
        ]);
    }

    private function fillPath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false);
    }

    private function submitPath(Event $event, Form $form): string
    {
        return route('dashboard.forms.submission', ['event' => $event, 'form' => $form], false);
    }

    private function submissionsPath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.submissions', ['event' => $event, 'form' => $form], false);
    }

    // =========================================================================
    // FILL CONTROLLER — accessStatus prop
    // =========================================================================

    public function test_fill_returns_allowed_for_open_public_form(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        $response = $this->actingAs($member)->get($this->fillPath($event, $form));

        $response->assertOk()
                 ->assertInertia(fn ($page) => $page
                     ->component('Dashboard/Events/Forms/Fill')
                     ->where('accessStatus', 'allowed')
                     ->where('accessMessage', '')
                 );
    }

    public function test_fill_returns_form_closed_when_past_closed_at(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, ['closed_at' => now()->subHour()]);

        $response = $this->actingAs($member)->get($this->fillPath($event, $form));

        $response->assertOk()
                 ->assertInertia(fn ($page) => $page
                     ->where('accessStatus', 'form_closed')
                 );
    }

    public function test_fill_returns_registration_not_open_before_window(): void
    {
        $member = $this->member();
        $event  = $this->openEvent([
            'registration_start' => now()->addDays(5),
            'registration_end'   => now()->addDays(20),
        ]);
        $form = $this->openForm($event);

        $response = $this->actingAs($member)->get($this->fillPath($event, $form));

        $response->assertOk()
                 ->assertInertia(fn ($page) => $page
                     ->where('accessStatus', 'registration_not_open')
                 );
    }

    public function test_fill_returns_registration_not_open_after_window(): void
    {
        $member = $this->member();
        $event  = $this->openEvent([
            'registration_start' => now()->subDays(20),
            'registration_end'   => now()->subDays(5),
        ]);
        $form = $this->openForm($event);

        $response = $this->actingAs($member)->get($this->fillPath($event, $form));

        $response->assertOk()
                 ->assertInertia(fn ($page) => $page
                     ->where('accessStatus', 'registration_not_open')
                 );
    }

    public function test_fill_returns_quota_full_when_at_capacity(): void
    {
        $member = $this->member();
        $event  = $this->openEvent(['quota' => 10, 'registered_count' => 10]);
        $form   = $this->openForm($event);

        $response = $this->actingAs($member)->get($this->fillPath($event, $form));

        $response->assertOk()
                 ->assertInertia(fn ($page) => $page
                     ->where('accessStatus', 'quota_full')
                 );
    }

    public function test_fill_returns_already_submitted_when_duplicate(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => [],
        ]);

        $response = $this->actingAs($member)->get($this->fillPath($event, $form));

        $response->assertOk()
                 ->assertInertia(fn ($page) => $page
                     ->where('accessStatus', 'already_submitted')
                 );
    }

    public function test_fill_returns_not_visible_when_admin_only_form_accessed_by_member(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'visible_for' => [EventFormVisibility::Admin->value],
        ]);

        $response = $this->actingAs($member)->get($this->fillPath($event, $form));

        $response->assertOk()
                 ->assertInertia(fn ($page) => $page
                     ->where('accessStatus', 'not_visible')
                 );
    }

    public function test_admin_can_fill_admin_only_form(): void
    {
        $admin = $this->admin();
        $event = $this->openEvent();
        $form  = $this->openForm($event, [
            'visible_for' => [EventFormVisibility::Admin->value],
        ]);

        $response = $this->actingAs($admin)->get($this->fillPath($event, $form));

        $response->assertOk()
                 ->assertInertia(fn ($page) => $page
                     ->where('accessStatus', 'allowed')
                 );
    }

    public function test_fill_requires_authentication(): void
    {
        $event = $this->openEvent();
        $form  = $this->openForm($event);

        $this->get($this->fillPath($event, $form))->assertRedirect(route('auth.login'));
    }

    public function test_fill_404_when_form_does_not_belong_to_event(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $other  = $this->openEvent();
        $form   = $this->openForm($other);

        $this->actingAs($member)
             ->get($this->fillPath($event, $form))
             ->assertNotFound();
    }

    // =========================================================================
    // SUBMISSION CONTROLLER — happy path
    // =========================================================================

    public function test_member_can_submit_form_and_answer_is_saved(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $this->textField($form);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['full_name' => 'Jane Doe'])
             ->assertRedirect(route('dashboard.events.show', $event));

        $this->assertDatabaseHas('form_answers', [
            'form_id' => $form->id,
            'user_id' => $member->id,
        ]);

        $answer = FormAnswer::where('form_id', $form->id)->where('user_id', $member->id)->first();
        $this->assertSame('Jane Doe', $answer->answers['full_name']);
    }

    public function test_registered_count_is_incremented_on_successful_submission(): void
    {
        $member = $this->member();
        $event  = $this->openEvent(['registered_count' => 0]);
        $form   = $this->openForm($event);
        $this->textField($form);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['full_name' => 'Jane Doe']);

        $this->assertDatabaseHas('events', [
            'id'               => $event->id,
            'registered_count' => 1,
        ]);
    }

    public function test_checkbox_field_stored_as_array(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'checkbox',
            'name'       => 'interests',
            'label'      => 'Interests',
            'order'      => 1,
            'metadata'   => ['options' => 'design,coding,other', 'rules' => ['required' => true]],
        ]);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['interests' => ['design', 'coding']])
             ->assertRedirect(route('dashboard.events.show', $event));

        $answer = FormAnswer::where('form_id', $form->id)->first();
        $this->assertIsArray($answer->answers['interests']);
        $this->assertSame(['design', 'coding'], $answer->answers['interests']);
    }

    public function test_multi_select_field_stored_as_array(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'selectInput',
            'name'       => 'skills',
            'label'      => 'Skills',
            'order'      => 1,
            'metadata'   => ['is_multiple' => true, 'rules' => ['required' => true]],
        ]);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['skills' => ['php', 'vue']])
             ->assertRedirect(route('dashboard.events.show', $event));

        $answer = FormAnswer::where('form_id', $form->id)->first();
        $this->assertIsArray($answer->answers['skills']);
        $this->assertSame(['php', 'vue'], $answer->answers['skills']);
    }

    public function test_file_upload_field_stores_path(): void
    {
        Storage::fake('public');

        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'fileUpload',
            'name'       => 'cv',
            'label'      => 'CV',
            'order'      => 1,
            'metadata'   => ['rules' => ['required' => true, 'mimes' => 'pdf', 'max_size' => 2048]],
        ]);

        $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['cv' => $file])
             ->assertRedirect(route('dashboard.events.show', $event));

        $answer = FormAnswer::where('form_id', $form->id)->first();
        $this->assertStringStartsWith("form-uploads/{$form->id}/", $answer->answers['cv']);
        Storage::disk('public')->assertExists($answer->answers['cv']);
    }

    // =========================================================================
    // SUBMISSION CONTROLLER — blocked states
    // =========================================================================

    public function test_submit_blocked_when_form_closed(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, ['closed_at' => now()->subHour()]);
        $this->textField($form);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['full_name' => 'Jane'])
             ->assertRedirect($this->fillPath($event, $form));

        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
    }

    public function test_submit_blocked_when_registration_not_open(): void
    {
        $member = $this->member();
        $event  = $this->openEvent([
            'registration_start' => now()->addDays(5),
            'registration_end'   => now()->addDays(20),
        ]);
        $form = $this->openForm($event);
        $this->textField($form);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['full_name' => 'Jane'])
             ->assertRedirect($this->fillPath($event, $form));

        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
    }

    public function test_submit_blocked_when_quota_full(): void
    {
        $member = $this->member();
        $event  = $this->openEvent(['quota' => 5, 'registered_count' => 5]);
        $form   = $this->openForm($event);
        $this->textField($form);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['full_name' => 'Jane'])
             ->assertRedirect($this->fillPath($event, $form));

        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'registered_count' => 5]);
    }

    public function test_submit_blocked_for_duplicate_submission(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $this->textField($form);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => [],
        ]);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['full_name' => 'Jane'])
             ->assertRedirect($this->fillPath($event, $form));

        $this->assertDatabaseCount('form_answers', 1);
    }

    public function test_submit_blocked_for_not_visible_form(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'visible_for' => [EventFormVisibility::Admin->value],
        ]);
        $this->textField($form);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['full_name' => 'Jane'])
             ->assertRedirect($this->fillPath($event, $form));

        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
    }

    // =========================================================================
    // SUBMISSION CONTROLLER — validation
    // =========================================================================

    public function test_validation_fails_for_missing_required_field(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $this->textField($form); // required full_name

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), [])
             ->assertRedirect($this->fillPath($event, $form))
             ->assertSessionHasErrors('full_name');
    }

    public function test_validation_fails_for_invalid_radio_option(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'radio',
            'name'       => 'gender',
            'label'      => 'Gender',
            'order'      => 1,
            'metadata'   => ['options' => 'male,female', 'rules' => ['required' => true]],
        ]);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['gender' => 'other'])
             ->assertRedirect()
             ->assertSessionHasErrors('gender');
    }

    public function test_validation_fails_for_invalid_checkbox_option(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'checkbox',
            'name'       => 'hobbies',
            'label'      => 'Hobbies',
            'order'      => 1,
            'metadata'   => ['options' => 'reading,gaming', 'rules' => ['required' => true]],
        ]);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['hobbies' => ['reading', 'hacking']])
             ->assertRedirect()
             ->assertSessionHasErrors('hobbies.*');
    }

    public function test_submit_requires_authentication(): void
    {
        $event = $this->openEvent();
        $form  = $this->openForm($event);

        $this->post($this->submitPath($event, $form), [])
             ->assertRedirect(route('auth.login'));
    }

    public function test_submit_404_when_form_does_not_belong_to_event(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $other  = $this->openEvent();
        $form   = $this->openForm($other);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), [])
             ->assertNotFound();
    }

    // =========================================================================
    // FORM SUBMISSIONS CONTROLLER (admin list)
    // =========================================================================

    public function test_admin_can_view_submissions_list(): void
    {
        $admin  = $this->admin();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $member = $this->member();

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => ['full_name' => 'Jane Doe'],
        ]);

        $response = $this->actingAs($admin)->get($this->submissionsPath($event, $form));

        $response->assertOk()
                 ->assertInertia(fn ($page) => $page
                     ->component('Dashboard/Events/Forms/Submissions')
                     ->has('submissions.data', 1)
                     ->where('submissions.data.0.user.email', $member->email)
                     ->where('submissions.data.0.answers.full_name', 'Jane Doe')
                 );
    }

    public function test_member_cannot_view_submissions_list(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        $this->actingAs($member)
             ->get($this->submissionsPath($event, $form))
             ->assertForbidden();
    }

    public function test_submissions_list_404_when_form_does_not_belong_to_event(): void
    {
        $admin = $this->admin();
        $event = $this->openEvent();
        $other = $this->openEvent();
        $form  = $this->openForm($other);

        $this->actingAs($admin)
             ->get($this->submissionsPath($event, $form))
             ->assertNotFound();
    }

    public function test_submissions_list_requires_authentication(): void
    {
        $event = $this->openEvent();
        $form  = $this->openForm($event);

        $this->get($this->submissionsPath($event, $form))
             ->assertRedirect(route('auth.login'));
    }

    // =========================================================================
    // DB CONSTRAINT — unique (user_id, form_id)
    // =========================================================================

    public function test_db_unique_constraint_prevents_duplicate_form_answers(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $member = $this->member();
        $form   = $this->openForm($this->openEvent());

        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $member->id, 'answers' => []]);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $member->id, 'answers' => []]);
    }

    // =========================================================================
    // ADMIN EXEMPTIONS
    // =========================================================================

    public function test_admin_can_submit_even_when_registration_not_open(): void
    {
        $admin = $this->admin();
        $event = $this->openEvent([
            'registration_start' => now()->addDays(10),
            'registration_end'   => now()->addDays(20),
        ]);
        $form = $this->openForm($event);
        $this->textField($form);

        $this->actingAs($admin)
             ->post($this->submitPath($event, $form), ['full_name' => 'Admin User'])
             ->assertRedirect(route('dashboard.events.show', $event));

        $this->assertDatabaseHas('form_answers', ['form_id' => $form->id, 'user_id' => $admin->id]);
    }

    public function test_admin_can_submit_even_when_quota_full(): void
    {
        $admin = $this->admin();
        $event = $this->openEvent(['quota' => 1, 'registered_count' => 1]);
        $form  = $this->openForm($event);
        $this->textField($form);

        $this->actingAs($admin)
             ->post($this->submitPath($event, $form), ['full_name' => 'Admin User'])
             ->assertRedirect(route('dashboard.events.show', $event));

        $this->assertDatabaseHas('form_answers', ['form_id' => $form->id, 'user_id' => $admin->id]);
    }
}

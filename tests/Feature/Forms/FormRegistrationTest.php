<?php

namespace Tests\Feature\Forms;

use App\Enums\EmailNotificationType;
use App\Enums\EventStatus;
use App\Enums\FormAccessStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\EventFormVisibility;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use App\Mail\RegistrationAcceptedMail;
use App\Mail\RegistrationConfirmationMail;
use App\Mail\RegistrationRejectedMail;
use App\Mail\TeamInvitationMail;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
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

    private function memberWithEmail(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
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

    /** Text field repeated per bundle participant (metadata `duplicatable`). */
    private function duplicatableTextField(Form $form, string $name = 'full_name', int $order = 1): FormField
    {
        return FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => $name,
            'label'      => 'Full Name',
            'order'      => $order,
            'metadata'   => ['type' => 'text', 'rules' => ['required' => true], 'duplicatable' => true],
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

    private function reviewPath(Event $event, Form $form, FormAnswer $answer): string
    {
        return route('dashboard.events.forms.submissions.review', [
            'event' => $event,
            'form' => $form,
            'formAnswer' => $answer,
        ], false);
    }

    private function registrantsPath(Event $event): string
    {
        return route('dashboard.events.registrants', ['event' => $event], false);
    }

    /** Post-submit redirect matches {@see FormSubmissionController} (members use the user portal). */
    private function submitSuccessRedirect(Event $event, User $user): string
    {
        return $user->can('events.view')
            ? route('dashboard.events.show', $event)
            : route('dashboard.user.events.show', ['event_segment' => $event->slug]);
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
                 ->assertInertia(
                     fn ($page) => $page
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
                 ->assertInertia(
                     fn ($page) => $page
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
                 ->assertInertia(
                     fn ($page) => $page
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
                 ->assertInertia(
                     fn ($page) => $page
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
                 ->assertInertia(
                     fn ($page) => $page
                     ->where('accessStatus', 'quota_full')
                 );
    }

    public function test_fill_returns_allowed_for_team_form_when_member_and_team_size_configured(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $this->textField($form);

        $this->actingAs($member)
            ->get($this->fillPath($event, $form))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Dashboard/Events/Forms/Fill')
                    ->where('accessStatus', 'allowed')
                    ->where('registrationMode', 'team')
                    ->where('memberSlots', 1)
            );
    }

    public function test_fill_returns_allowed_for_bundle_form_when_member(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => ['registration_mode' => 'bundle', 'max_team_size' => 3],
        ]);
        $this->textField($form);

        $this->actingAs($member)
            ->get($this->fillPath($event, $form))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Dashboard/Events/Forms/Fill')
                    ->where('accessStatus', 'allowed')
                    ->where('registrationMode', 'bundle')
                    ->where('memberSlots', 2)
            );
    }

    public function test_fill_returns_allowed_when_registration_mode_is_single_for_member(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => ['registration_mode' => 'single'],
        ]);
        $this->textField($form);

        $this->actingAs($member)
            ->get($this->fillPath($event, $form))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('accessStatus', 'allowed')
            );
    }

    public function test_admin_can_fill_team_registration_form(): void
    {
        $admin = $this->admin();
        $event = $this->openEvent();
        $form  = $this->openForm($event, [
            'metadata' => ['registration_mode' => 'team'],
        ]);
        $this->textField($form);

        $this->actingAs($admin)
            ->get($this->fillPath($event, $form))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('accessStatus', 'allowed')
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
                 ->assertInertia(
                     fn ($page) => $page
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
                 ->assertInertia(
                     fn ($page) => $page
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
                 ->assertInertia(
                     fn ($page) => $page
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
             ->assertRedirect($this->submitSuccessRedirect($event, $member));

        $this->assertDatabaseHas('form_answers', [
            'form_id' => $form->id,
            'user_id' => $member->id,
        ]);

        $answer = FormAnswer::where('form_id', $form->id)->where('user_id', $member->id)->first();
        $this->assertSame('Jane Doe', $answer->answers['full_name']);
    }

    public function test_fill_second_form_in_event_is_blocked_after_submission_to_first(): void
    {
        $member = $this->member();
        $event = $this->openEvent(['status' => EventStatus::Published]);
        $formA = $this->openForm($event, ['title' => 'Form A']);
        $formB = $this->openForm($event, ['title' => 'Form B']);
        $this->textField($formA);
        $this->textField($formB);

        $this->actingAs($member)
            ->post($this->submitPath($event, $formA), ['full_name' => 'Jane Doe'])
            ->assertRedirect($this->submitSuccessRedirect($event, $member));

        $this->actingAs($member)
            ->get($this->fillPath($event, $formB))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('accessStatus', 'event_form_already_chosen'));
    }

    public function test_cannot_submit_second_form_same_event(): void
    {
        $member = $this->member();
        $event = $this->openEvent(['status' => EventStatus::Published]);
        $formA = $this->openForm($event, ['title' => 'Form A']);
        $formB = $this->openForm($event, ['title' => 'Form B']);
        $this->textField($formA);
        $this->textField($formB);

        $this->actingAs($member)
            ->post($this->submitPath($event, $formA), ['full_name' => 'Jane Doe'])
            ->assertRedirect($this->submitSuccessRedirect($event, $member));

        $this->actingAs($member)
            ->post($this->submitPath($event, $formB), ['full_name' => 'John Doe'])
            ->assertRedirect($this->fillPath($event, $formB));
    }

    public function test_register_picker_renders_all_event_forms(): void
    {
        $member = $this->member();
        $event = $this->openEvent(['status' => EventStatus::Published]);
        $formA = $this->openForm($event, ['title' => 'Early Bird']);
        $formB = $this->openForm($event, ['title' => 'General']);

        $this->actingAs($member)
            ->get(route('dashboard.user.events.register', ['event_segment' => $event->slug], false))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/User/EventRegistrationPickForm')
                ->has('forms', 2)
                ->where('forms.0.title', 'Early Bird')
                ->where('forms.1.title', 'General'));
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
             ->assertRedirect($this->submitSuccessRedirect($event, $member));

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
             ->assertRedirect($this->submitSuccessRedirect($event, $member));

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
             ->assertRedirect($this->submitSuccessRedirect($event, $member));

        $answer = FormAnswer::where('form_id', $form->id)->first();
        $this->assertStringStartsWith("form-uploads/{$form->id}/", $answer->answers['cv']);
        Storage::disk('public')->assertExists($answer->answers['cv']);
    }

    public function test_date_picker_field_is_stored_as_scalar_string(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'datePicker',
            'name'       => 'birth_date',
            'label'      => 'Birth date',
            'order'      => 1,
            'metadata'   => ['rules' => ['required' => true, 'min_date' => '2000-01-01', 'max_date' => '2010-12-31']],
        ]);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['birth_date' => '2005-06-15'])
            ->assertRedirect($this->submitSuccessRedirect($event, $member));

        $answer = FormAnswer::where('form_id', $form->id)->first();
        $this->assertSame('2005-06-15', $answer->answers['birth_date']);
    }

    public function test_validation_fails_for_date_picker_outside_min_max(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'datePicker',
            'name'       => 'birth_date',
            'label'      => 'Birth date',
            'order'      => 1,
            'metadata'   => ['rules' => ['required' => true, 'min_date' => '2000-01-01', 'max_date' => '2010-12-31']],
        ]);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['birth_date' => '1995-06-15'])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('birth_date');

        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
    }

    public function test_file_upload_rejects_non_matching_mime(): void
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
            'metadata'   => ['rules' => ['required' => true, 'mimes' => 'pdf']],
        ]);

        $file = UploadedFile::fake()->create('cv.txt', 10, 'text/plain');

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['cv' => $file])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('cv');

        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
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

    public function test_team_submission_creates_leader_and_member_rows_and_sends_mails(): void
    {
        Mail::fake();

        $leader   = $this->memberWithEmail('team-leader-'.uniqid().'@example.com');
        $teammate = $this->memberWithEmail('team-mate-'.uniqid().'@example.com');
        $event    = $this->openEvent();
        $form     = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Team Leader',
                'team_member_emails' => [$teammate->email],
            ])
            ->assertRedirect($this->submitSuccessRedirect($event, $leader));

        $this->assertDatabaseCount('form_answers', 2);

        $leaderRow = FormAnswer::query()
            ->where('form_id', $form->id)
            ->where('user_id', $leader->id)
            ->firstOrFail();
        $memberRow = FormAnswer::query()
            ->where('form_id', $form->id)
            ->where('user_id', $teammate->id)
            ->firstOrFail();

        $this->assertSame(RegistrationRole::Leader, $leaderRow->registration_role);
        $this->assertNull($leaderRow->leader_form_answer_id);
        $this->assertSame(MemberConfirmationStatus::Accepted, $leaderRow->member_confirmation_status);

        $this->assertSame(RegistrationRole::Member, $memberRow->registration_role);
        $this->assertSame((string) $leaderRow->id, (string) $memberRow->leader_form_answer_id);
        $this->assertSame(MemberConfirmationStatus::Pending, $memberRow->member_confirmation_status);
        $this->assertNotNull($memberRow->invitation_token);

        Mail::assertSent(RegistrationConfirmationMail::class, 1);
        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $mail) use ($memberRow): bool {
            return (string) $mail->memberSubmission->id === (string) $memberRow->id;
        });

        $this->assertDatabaseHas('email_logs', [
            'form_answer_id' => $leaderRow->id,
            'notification_type' => EmailNotificationType::RegistrationSubmitted->value,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'form_answer_id' => $memberRow->id,
            'notification_type' => EmailNotificationType::TeamInvitation->value,
        ]);
    }

    public function test_team_submission_increments_event_registered_count_by_team_size(): void
    {
        Mail::fake();

        $leader   = $this->memberWithEmail('team-leader-rc-'.uniqid().'@example.com');
        $teammate = $this->memberWithEmail('team-mate-rc-'.uniqid().'@example.com');
        $event    = $this->openEvent(['registered_count' => 0]);
        $form     = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Team Leader',
                'team_member_emails' => [$teammate->email],
            ])
            ->assertRedirect($this->submitSuccessRedirect($event, $leader));

        $event->refresh();
        $this->assertSame(2, (int) $event->registered_count);
    }

    public function test_team_submission_redirects_with_errors_when_member_emails_missing(): void
    {
        $leader = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), ['full_name' => 'Jane'])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails');

        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
    }

    public function test_team_submission_redirects_with_errors_for_unknown_member_email(): void
    {
        $leader = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Jane',
                'team_member_emails' => ['not-a-user@example.com'],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails.0');

        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
    }

    public function test_team_submission_redirects_with_errors_when_teammate_already_on_form(): void
    {
        $leader   = $this->memberWithEmail('team-leader-dup-'.uniqid().'@example.com');
        $teammate = $this->memberWithEmail('team-mate-dup-'.uniqid().'@example.com');
        $event    = $this->openEvent();
        $form     = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $this->textField($form);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $teammate->id,
            'answers' => ['full_name' => 'Existing'],
        ]);

        $this->from($this->fillPath($event, $form))
            ->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Jane',
                'team_member_emails' => [$teammate->email],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails');

        $this->assertDatabaseCount('form_answers', 1);
    }

    public function test_fill_returns_pending_team_confirmation_for_unconfirmed_team_member(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $this->textField($form);

        $leader = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $this->member()->id,
            'registration_role' => RegistrationRole::Leader,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
        ]);

        $token = 'test-invite-token-'.uniqid();

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'leader_form_answer_id' => $leader->id,
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Pending,
            'invitation_token' => $token,
            'invitation_expired_at' => now()->addDays(7),
            'answers' => ['full_name' => 'Member'],
        ]);

        $this->actingAs($member)
            ->get($this->fillPath($event, $form))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('accessStatus', 'pending_team_confirmation')
                    ->where('accessMessage', FormAccessStatus::PendingTeamConfirmation->message())
                    ->where('pendingInvitationUrl', '/user/dashboard/team-invitations/'.$token)
            );
    }

    public function test_team_member_can_confirm_invitation_without_append_fields(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $this->textField($form);

        $leader = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $this->member()->id,
            'registration_role' => RegistrationRole::Leader,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
        ]);

        $token = 'confirm-token-'.uniqid();

        $row = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'leader_form_answer_id' => $leader->id,
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Pending,
            'invitation_token' => $token,
            'invitation_expired_at' => now()->addDays(7),
            'answers' => ['full_name' => 'Member'],
        ]);

        $this->actingAs($member)
            ->post(route('dashboard.user.team-invitations.update', ['token' => $token], false), [
                'invitation_decision' => 'accept',
            ])
            ->assertRedirect(route('dashboard.user.events.show', ['event_segment' => $event->slug], false));

        $row->refresh();
        $this->assertSame(MemberConfirmationStatus::Accepted, $row->member_confirmation_status);
    }

    public function test_bundle_submission_creates_leader_member_rows_with_same_group_token(): void
    {
        Mail::fake();

        $leader = $this->memberWithEmail('bundle-leader-'.uniqid().'@example.com');
        $mate   = $this->memberWithEmail('bundle-mate-'.uniqid().'@example.com');
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size' => 2,
            ],
        ]);
        $this->duplicatableTextField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Lead Person',
                'bundle__full_name__0' => 'Second Person',
                'team_member_emails' => [$mate->email],
            ])
            ->assertRedirect($this->submitSuccessRedirect($event, $leader));

        $this->assertDatabaseCount('form_answers', 2);

        $leaderRow = FormAnswer::query()->where('form_id', $form->id)->where('user_id', $leader->id)->firstOrFail();
        $memberRow = FormAnswer::query()->where('form_id', $form->id)->where('user_id', $mate->id)->firstOrFail();

        $this->assertNotNull($leaderRow->group_token);
        $this->assertSame($leaderRow->group_token, $memberRow->group_token);
        $this->assertSame(MemberConfirmationStatus::Accepted, $leaderRow->member_confirmation_status);
        $this->assertSame(MemberConfirmationStatus::Pending, $memberRow->member_confirmation_status);
        $this->assertSame('Lead Person', $leaderRow->answers['full_name'] ?? null);
        $this->assertSame('Second Person', $memberRow->answers['full_name'] ?? null);

        Mail::assertSent(TeamInvitationMail::class, 1);
    }

    public function test_expired_invitation_returns_403_and_marks_expired(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => ['registration_mode' => 'team', 'team_size' => 2],
        ]);
        $this->textField($form);

        $leader = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $this->member()->id,
            'registration_role' => RegistrationRole::Leader,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
        ]);

        $token = 'expired-token-'.uniqid();

        $row = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'leader_form_answer_id' => $leader->id,
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Pending,
            'invitation_token' => $token,
            'invitation_expired_at' => now()->subHour(),
            'answers' => ['full_name' => 'M'],
        ]);

        $this->actingAs($member)
            ->get(route('dashboard.user.team-invitations.show', ['token' => $token], false))
            ->assertForbidden();

        $row->refresh();
        $this->assertSame(MemberConfirmationStatus::Expired, $row->member_confirmation_status);
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
                 ->assertInertia(
                     fn ($page) => $page
                     ->component('Dashboard/Events/Forms/Submissions')
                     ->has('submissions.data', 1)
                     ->where('submissions.data.0.user.email', $member->email)
                     ->where('submissions.data.0.answers.full_name', 'Jane Doe')
                 );
    }

    // =========================================================================
    // REVIEW SUBMISSION (Accept/Reject)
    // =========================================================================

    public function test_admin_can_accept_pending_submission(): void
    {
        $admin  = $this->admin();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $member = $this->member();

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => ['full_name' => 'Jane Doe'],
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        Mail::fake();

        $this->actingAs($admin)
            ->patchJson($this->reviewPath($event, $form, $answer), [
                'review_status' => FormAnswerReviewStatus::Accepted->value,
            ])
            ->assertOk()
            ->assertJsonPath('review_status', FormAnswerReviewStatus::Accepted->value)
            ->assertJsonStructure([
                'id',
                'review_status',
                'reviewed_at',
                'reviewed_by',
                'registration_code',
            ]);

        $answer->refresh();

        $this->assertMatchesRegularExpression(
            '/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}$/',
            (string) $answer->registration_code,
        );

        Mail::assertSent(RegistrationAcceptedMail::class, function (RegistrationAcceptedMail $mail) use ($answer): bool {
            return $mail->submission->id === $answer->id
                && $mail->registrationCode === $answer->registration_code
                && strlen($mail->qrPngBinary) > 100;
        });

        $this->assertDatabaseHas('email_logs', [
            'form_answer_id' => $answer->id,
            'notification_type' => EmailNotificationType::RegistrationAccepted->value,
        ]);

        $this->assertDatabaseHas('form_answers', [
            'id' => $answer->id,
            'review_status' => FormAnswerReviewStatus::Accepted->value,
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_admin_cannot_accept_team_member_before_confirmation(): void
    {
        $admin  = $this->admin();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $memberUser = $this->member();
        $leaderUser = $this->member();

        $leaderAnswer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $leaderUser->id,
            'registration_role' => RegistrationRole::Leader,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $memberAnswer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $memberUser->id,
            'leader_form_answer_id' => $leaderAnswer->id,
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Pending,
            'invitation_token' => 'tok-test-'.uniqid(),
            'invitation_expired_at' => now()->addDays(7),
            'review_status' => FormAnswerReviewStatus::Pending,
            'answers' => ['full_name' => 'Member'],
        ]);

        Mail::fake();

        $this->actingAs($admin)
            ->patchJson($this->reviewPath($event, $form, $memberAnswer), [
                'review_status' => FormAnswerReviewStatus::Accepted->value,
            ])
            ->assertStatus(422);

        $memberAnswer->refresh();
        $this->assertSame(FormAnswerReviewStatus::Pending, $memberAnswer->review_status);

        Mail::assertNothingSent();
    }

    public function test_admin_can_accept_team_member_after_invitation_accepted(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $event = $this->openEvent();
        $form  = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $memberUser = $this->member();
        $leaderUser = $this->member();

        $leaderAnswer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $leaderUser->id,
            'registration_role' => RegistrationRole::Leader,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $memberAnswer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $memberUser->id,
            'leader_form_answer_id' => $leaderAnswer->id,
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'member_confirmed_at' => now(),
            'invitation_token' => 'tok-ok-'.uniqid(),
            'review_status' => FormAnswerReviewStatus::Pending,
            'answers' => ['full_name' => 'Member'],
        ]);

        $this->actingAs($admin)
            ->patchJson($this->reviewPath($event, $form, $memberAnswer), [
                'review_status' => FormAnswerReviewStatus::Accepted->value,
            ])
            ->assertOk();

        Mail::assertSent(RegistrationAcceptedMail::class, 1);
    }

    public function test_admin_reject_pending_submission_sends_rejected_mail(): void
    {
        Mail::fake();

        $admin  = $this->admin();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $member = $this->member();

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => ['full_name' => 'Jane Doe'],
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->patchJson($this->reviewPath($event, $form, $answer), [
                'review_status' => FormAnswerReviewStatus::Rejected->value,
            ])
            ->assertOk()
            ->assertJsonPath('review_status', FormAnswerReviewStatus::Rejected->value);

        Mail::assertSent(RegistrationRejectedMail::class, function (RegistrationRejectedMail $mail) use ($answer): bool {
            return $mail->submission->id === $answer->id;
        });

        $this->assertDatabaseHas('email_logs', [
            'form_answer_id' => $answer->id,
            'notification_type' => EmailNotificationType::RegistrationRejected->value,
        ]);

        $answer->refresh();
        $this->assertNull($answer->registration_code);
    }

    public function test_review_is_immutable_and_second_review_returns_409(): void
    {
        $admin  = $this->admin();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $member = $this->member();

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => ['full_name' => 'Jane Doe'],
            'review_status' => FormAnswerReviewStatus::Rejected,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patchJson($this->reviewPath($event, $form, $answer), [
                'review_status' => FormAnswerReviewStatus::Accepted->value,
            ])
            ->assertStatus(409);
    }

    public function test_member_cannot_review_submission(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => [],
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $this->actingAs($member)
            ->patchJson($this->reviewPath($event, $form, $answer), [
                'review_status' => FormAnswerReviewStatus::Accepted->value,
            ])
            ->assertRedirect(route('dashboard.user.events'));
    }

    public function test_review_returns_404_when_submission_not_belong_to_form_or_event(): void
    {
        $admin = $this->admin();
        $event = $this->openEvent();
        $form  = $this->openForm($event);

        $otherEvent = $this->openEvent();
        $otherForm  = $this->openForm($otherEvent);
        $answer = FormAnswer::factory()->create([
            'form_id' => $otherForm->id,
            'user_id' => $this->member()->id,
            'answers' => [],
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->patchJson($this->reviewPath($event, $form, $answer), [
                'review_status' => FormAnswerReviewStatus::Accepted->value,
            ])
            ->assertNotFound();
    }

    public function test_member_cannot_view_submissions_list(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        $this->actingAs($member)
             ->get($this->submissionsPath($event, $form))
             ->assertRedirect(route('dashboard.user.events'));
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

    public function test_successful_submission_sends_registration_confirmation_mail(): void
    {
        Mail::fake();

        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $this->textField($form);

        $this->actingAs($member)
             ->post($this->submitPath($event, $form), ['full_name' => 'Jane Doe'])
             ->assertRedirect($this->submitSuccessRedirect($event, $member));

        Mail::assertSent(RegistrationConfirmationMail::class);
        Mail::assertNotSent(RegistrationAcceptedMail::class);

        $submission = FormAnswer::query()->where('form_id', $form->id)->where('user_id', $member->id)->first();
        $this->assertNotNull($submission);

        $this->assertDatabaseHas('email_logs', [
            'form_answer_id' => $submission->id,
            'notification_type' => EmailNotificationType::RegistrationSubmitted->value,
        ]);
    }

    public function test_admin_can_view_event_registrants_page(): void
    {
        $admin = $this->admin();
        $event = $this->openEvent();
        $form  = $this->openForm($event);
        $member = $this->member();
        $this->textField($form);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => ['full_name' => 'Jane Doe'],
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $this->actingAs($admin)
             ->get($this->registrantsPath($event))
             ->assertOk()
             ->assertInertia(
                 fn ($page) => $page
                     ->component('Dashboard/Events/Registrants')
                     ->has('registrants', 1)
                     ->where('registrationForm.id', $form->id),
             );
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

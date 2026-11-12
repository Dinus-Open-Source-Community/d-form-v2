<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Mail\RegistrationConfirmationMail;
use App\Mail\TeamInvitationMail;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Boundary contract of the form submit endpoint: only validated payload data is
 * used to build `form_answers.answers`, while guard ordering and error keys stay
 * exactly as before.
 */
class FormSubmissionBoundaryTest extends TestCase
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

    private function openEvent(array $overrides = []): Event
    {
        return Event::factory()->create(array_merge([
            'status'             => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end'   => now()->addDays(30),
            'quota'              => 100,
            'registered_count'   => 0,
        ], $overrides));
    }

    private function openForm(Event $event, array $overrides = []): Form
    {
        return Form::factory()->create(array_merge([
            'event_id'    => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at'   => now()->addDays(30),
        ], $overrides));
    }

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

    private function multipleFileField(Form $form, string $name = 'documents'): FormField
    {
        return FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'fileUpload',
            'name' => $name,
            'label' => 'Documents',
            'order' => 2,
            'metadata' => [
                'multiple' => true,
                'allowed_mime_types' => ['application/pdf'],
                'rules' => ['required' => true, 'mimes' => 'pdf'],
            ],
        ]);
    }

    private function duplicatableTextField(Form $form, string $name = 'full_name'): FormField
    {
        return FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => $name,
            'label'      => 'Full Name',
            'order'      => 1,
            'metadata'   => ['type' => 'text', 'rules' => ['required' => true], 'duplicatable' => true],
        ]);
    }

    /**
     * Builder-shaped checkbox without an `options` whitelist.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function checkboxField(Form $form, array $metadata, string $name = 'interests'): FormField
    {
        return FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'checkbox',
            'name'       => $name,
            'label'      => 'Interests',
            'order'      => 1,
            'metadata'   => $metadata,
        ]);
    }

    private function teamForm(Event $event, int $teamSize): Form
    {
        return $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size'         => $teamSize,
            ],
        ]);
    }

    private function bundleForm(Event $event, int $teamSize): Form
    {
        return $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size'     => $teamSize,
            ],
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

    private function submitSuccessRedirect(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form], false);
    }

    private function passwordField(Form $form, string $name = 'account_password'): FormField
    {
        return FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => $name,
            'label'      => 'Account Password',
            'order'      => 9,
            'metadata'   => ['type' => 'password', 'rules' => ['required' => true]],
        ]);
    }

    // =========================================================================
    // Single mode
    // =========================================================================

    public function test_single_submission_stores_only_declared_field_keys(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $this->textField($form);

        $intruder = $this->member();

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Jane Doe',
                'user_id' => $intruder->id,
                'review_status' => FormAnswerReviewStatus::Accepted->value,
                'is_active' => null,
                'form_id' => $intruder->id,
                'answers' => ['full_name' => 'Injected'],
                'arbitrary_key' => 'nope',
            ])
            ->assertRedirect($this->submitSuccessRedirect($event, $form));

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();

        $this->assertSame(['full_name'], array_keys($answer->answers));
        $this->assertSame('Jane Doe', $answer->answers['full_name']);
    }

    public function test_foreign_payload_keys_do_not_reach_the_model(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $this->textField($form);

        $intruder = $this->member();
        $otherForm = $this->openForm($event);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Jane Doe',
                'user_id' => $intruder->id,
                'review_status' => FormAnswerReviewStatus::Accepted->value,
                'is_active' => 0,
                'form_id' => $otherForm->id,
                'answers' => ['full_name' => 'Injected'],
                'registered_count' => 999,
            ])
            ->assertRedirect($this->submitSuccessRedirect($event, $form));

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();

        $this->assertSame((string) $member->id, (string) $answer->user_id);
        $this->assertSame(FormAnswerReviewStatus::Pending, $answer->review_status);
        $this->assertSame(1, $answer->is_active);
        $this->assertSame('Jane Doe', $answer->answers['full_name']);
    }

    public function test_array_posted_to_scalar_field_is_rejected_without_writing_a_row(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $this->textField($form);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [
                'full_name' => ['Jane', 'Doe'],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('full_name');

        $this->assertDatabaseCount('form_answers', 0);
    }

    // =========================================================================
    // Team mode
    // =========================================================================

    public function test_team_submission_sends_confirmation_and_invitation_mails(): void
    {
        Mail::fake();

        $leader   = $this->memberWithEmail('boundary-leader-'.uniqid().'@example.com');
        $teammate = $this->memberWithEmail('boundary-mate-'.uniqid().'@example.com');
        $event    = $this->openEvent();
        $form     = $this->teamForm($event, 2);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Team Leader',
                'team_member_emails' => [$teammate->email],
            ])
            ->assertRedirect($this->submitSuccessRedirect($event, $form));

        $this->assertDatabaseCount('form_answers', 2);
        Mail::assertSent(RegistrationConfirmationMail::class, 1);
        Mail::assertSent(TeamInvitationMail::class, 1);
    }

    public function test_team_submission_requires_the_member_email_list(): void
    {
        $leader = $this->member();
        $event  = $this->openEvent();
        $form   = $this->teamForm($event, 2);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), ['full_name' => 'Team Leader'])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails');

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_team_participant_validation_cleans_files_stored_before_the_failure(): void
    {
        Storage::fake('local');
        $leader = $this->member();
        $event = $this->openEvent();
        $form = $this->teamForm($event, 2);
        $this->multipleFileField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'documents' => [UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.7\n%%EOF")],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails');

        self::assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_team_submission_rejects_an_unknown_member_account(): void
    {
        $leader = $this->member();
        $event  = $this->openEvent();
        $form   = $this->teamForm($event, 2);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Team Leader',
                'team_member_emails' => ['nobody-'.uniqid().'@example.com'],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails.0');

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_team_unknown_member_validation_cleans_mapped_uploads(): void
    {
        Storage::fake('local');
        $leader = $this->member();
        $event = $this->openEvent();
        $form = $this->teamForm($event, 2);
        $this->multipleFileField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'documents' => [UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.7\n%%EOF")],
                'team_member_emails' => ['nobody-'.uniqid().'@example.com'],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails.0');

        self::assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_team_submission_rejects_the_leader_as_member(): void
    {
        $leader = $this->member();
        $event  = $this->openEvent();
        $form   = $this->teamForm($event, 2);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Team Leader',
                'team_member_emails' => [$leader->email],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails.0');

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_team_submission_rejects_duplicate_member_emails(): void
    {
        $leader   = $this->member();
        $teammate = $this->memberWithEmail('boundary-dup-'.uniqid().'@example.com');
        $event    = $this->openEvent();
        $form     = $this->teamForm($event, 3);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Team Leader',
                'team_member_emails' => [$teammate->email, $teammate->email],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors([
                'team_member_emails' => 'Each team member email must be unique.',
            ]);

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_team_form_misconfigured_team_size_redirects_with_error(): void
    {
        $leader = $this->member();
        $event  = $this->openEvent();
        $form   = $this->teamForm($event, 1);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), ['full_name' => 'Team Leader'])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors([
                'team_member_emails' => 'This team form requires a team size of at least 2 in form settings.',
            ]);

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_team_member_emails_are_never_stored_in_answers(): void
    {
        Mail::fake();

        $leader   = $this->member();
        $teammate = $this->memberWithEmail('boundary-keys-'.uniqid().'@example.com');
        $event    = $this->openEvent();
        $form     = $this->teamForm($event, 2);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Team Leader',
                'team_member_emails' => [$teammate->email],
            ])
            ->assertRedirect($this->submitSuccessRedirect($event, $form));

        foreach (FormAnswer::query()->where('form_id', $form->id)->get() as $answer) {
            $this->assertArrayNotHasKey('team_member_emails', $answer->answers);
        }
    }

    // =========================================================================
    // Bundle mode
    // =========================================================================

    public function test_bundle_form_misconfigured_team_size_redirects_with_error(): void
    {
        $leader = $this->member();
        $event  = $this->openEvent();
        $form   = $this->bundleForm($event, 1);
        $this->duplicatableTextField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Lead Person',
                'bundle__full_name__0' => 'Second Person',
                'team_member_emails' => ['someone-'.uniqid().'@example.com'],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors([
                'team_member_emails' => 'This bundle form requires a team size of at least 2 in form settings.',
            ]);

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_bundle_submission_stores_duplicatable_slot_answers(): void
    {
        Mail::fake();

        $leader     = $this->member();
        $guestEmail = 'boundary-guest-'.uniqid().'@example.com';
        $event      = $this->openEvent();
        $form       = $this->bundleForm($event, 2);
        $this->duplicatableTextField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Lead Person',
                'bundle__full_name__0' => 'Second Person',
                'team_member_emails' => [$guestEmail],
            ])
            ->assertRedirect($this->submitSuccessRedirect($event, $form));

        $this->assertDatabaseCount('form_answers', 2);

        $leaderRow = FormAnswer::query()->where('form_id', $form->id)->where('user_id', $leader->id)->firstOrFail();
        $memberRow = FormAnswer::query()->where('form_id', $form->id)->where('invited_email', mb_strtolower($guestEmail))->firstOrFail();

        $this->assertSame('Lead Person', $leaderRow->answers['full_name']);
        $this->assertSame('Second Person', $memberRow->answers['full_name']);
    }

    public function test_bundle_submission_rejects_an_empty_required_slot(): void
    {
        $leader     = $this->member();
        $guestEmail = 'boundary-slot-'.uniqid().'@example.com';
        $event      = $this->openEvent();
        $form       = $this->bundleForm($event, 2);
        $this->duplicatableTextField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Lead Person',
                'team_member_emails' => [$guestEmail],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('bundle__full_name__0');

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_bundle_submission_rejects_an_already_registered_guest_email(): void
    {
        $leader     = $this->member();
        $guestEmail = 'boundary-existing-'.uniqid().'@example.com';
        $event      = $this->openEvent();
        $form       = $this->bundleForm($event, 2);
        $this->duplicatableTextField($form);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => $guestEmail,
            'answers' => ['full_name' => 'Existing Guest'],
        ]);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Lead Person',
                'bundle__full_name__0' => 'Second Person',
                'team_member_emails' => [$guestEmail],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails.0');

        $this->assertDatabaseCount('form_answers', 1);
    }

    /**
     * BE-P3-013 regression: the duplicate pre-check runs before the answer
     * mapper, so a rejected duplicate attempt never writes a private upload.
     */
    public function test_bundle_duplicate_guest_email_stores_no_new_private_file(): void
    {
        Storage::fake('local');
        Mail::fake();

        $leader     = $this->member();
        $guestEmail = 'boundary-no-file-'.uniqid().'@example.com';
        $event      = $this->openEvent();
        $form       = $this->bundleForm($event, 2);
        $this->duplicatableTextField($form);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'fileUpload',
            'name'       => 'cv',
            'label'      => 'CV',
            'order'      => 2,
            'metadata'   => ['rules' => ['required' => true, 'mimes' => 'pdf', 'max_size' => 2048]],
        ]);

        $existing = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => $guestEmail,
            'answers' => ['full_name' => 'Existing Guest', 'cv' => 'form-uploads/'.$form->id.'/existing.pdf'],
        ]);

        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Lead Person',
                'bundle__full_name__0' => 'Second Person',
                'team_member_emails' => [$guestEmail],
                'cv' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.7\n".str_repeat('x', 100)."\n%%EOF"),
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails.0');

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(1, FormAnswer::query()->where('form_id', $form->id)->count());
        $this->assertDatabaseHas('form_answers', ['id' => $existing->id]);
    }

    public function test_bundle_submission_rejects_the_leader_as_guest(): void
    {
        $leader = $this->member();
        $event  = $this->openEvent();
        $form   = $this->bundleForm($event, 2);
        $this->duplicatableTextField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Lead Person',
                'bundle__full_name__0' => 'Second Person',
                'team_member_emails' => [$leader->email],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails.0');

        $this->assertDatabaseCount('form_answers', 0);
    }

    // =========================================================================
    // Guard precedence and other-purpose forms
    // =========================================================================

    public function test_blocked_form_wins_over_an_invalid_payload(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, ['closed_at' => now()->subHour()]);
        $this->textField($form);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_other_purpose_form_still_submits(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => ['purpose' => 'other'],
        ]);
        $this->textField($form);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['full_name' => 'Survey Answer'])
            ->assertRedirect($this->submitSuccessRedirect($event, $form));

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame('Survey Answer', $answer->answers['full_name']);
    }

    public function test_non_registration_form_with_team_metadata_is_rejected_without_validation_errors(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event, [
            'metadata' => ['purpose' => 'other', 'registration_mode' => 'team', 'team_size' => 2],
        ]);
        $this->textField($form);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('form_answers', 0);
    }

    // =========================================================================
    // Payload shape guardrails
    // =========================================================================

    public function test_dotted_field_name_stores_its_nested_value(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => 'a.b',
            'label'      => 'Dotted name',
            'order'      => 1,
            'metadata'   => ['type' => 'text', 'rules' => ['required' => true]],
        ]);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['a' => ['b' => 'nested value']])
            ->assertRedirect($this->submitSuccessRedirect($event, $form));

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame('nested value', $answer->answers['a.b']);
    }

    public function test_bundle_rejects_sparse_member_email_indexes(): void
    {
        $leader     = $this->member();
        $guestEmail = 'boundary-sparse-'.uniqid().'@example.com';
        $event      = $this->openEvent();
        $form       = $this->bundleForm($event, 2);
        $this->duplicatableTextField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Lead Person',
                'bundle__full_name__0' => 'Second Person',
                'team_member_emails' => [5 => $guestEmail],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails');

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_bundle_slot_rejects_an_array_value(): void
    {
        $leader     = $this->member();
        $guestEmail = 'boundary-slot-array-'.uniqid().'@example.com';
        $event      = $this->openEvent();
        $form       = $this->bundleForm($event, 2);
        $this->duplicatableTextField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Lead Person',
                'bundle__full_name__0' => ['a', 'b'],
                'team_member_emails' => [$guestEmail],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('bundle__full_name__0');

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_bundle_member_emails_are_never_stored_in_answers(): void
    {
        Mail::fake();

        $leader     = $this->member();
        $guestEmail = 'boundary-bundle-keys-'.uniqid().'@example.com';
        $event      = $this->openEvent();
        $form       = $this->bundleForm($event, 2);
        $this->duplicatableTextField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Lead Person',
                'bundle__full_name__0' => 'Second Person',
                'team_member_emails' => [$guestEmail],
            ])
            ->assertRedirect($this->submitSuccessRedirect($event, $form));

        foreach (FormAnswer::query()->where('form_id', $form->id)->get() as $answer) {
            $this->assertArrayNotHasKey('team_member_emails', $answer->answers);
        }
    }

    public function test_checkbox_without_whitelist_rejects_nested_array_values(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        $this->checkboxField($form, [
            'type'  => 'checkbox',
            'rules' => ['required' => true, 'in' => 'design,coding'],
        ]);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [
                'interests' => [['design' => '1']],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('interests.0');

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_checkbox_without_whitelist_still_accepts_a_list_of_strings(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        $this->checkboxField($form, [
            'type'  => 'checkbox',
            'rules' => ['required' => true, 'in' => 'design,coding'],
        ]);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [
                'interests' => ['design', 'coding'],
            ])
            ->assertRedirect($this->submitSuccessRedirect($event, $form));

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame(['design', 'coding'], $answer->answers['interests']);
    }

    public function test_file_upload_field_stores_a_path_for_a_valid_pdf(): void
    {
        Storage::fake('local');
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

        $file = UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.7\n".str_repeat('x', 100)."\n%%EOF");

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['cv' => $file])
            ->assertRedirect($this->submitSuccessRedirect($event, $form));

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertStringStartsWith("form-uploads/{$form->id}/", $answer->answers['cv']);
    }

    public function test_file_upload_field_rejects_a_plain_string(): void
    {
        Storage::fake('local');
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

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['cv' => 'not-a-file'])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('cv');

        $this->assertDatabaseCount('form_answers', 0);
    }

    // =========================================================================
    // Failed-submit old input and post-submit redirect target
    // =========================================================================

    public function test_failed_single_submit_never_flashes_password_answers(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $this->textField($form);
        $this->passwordField($form);

        $secret = 'p@ssw0rd-'.uniqid();

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [
                'nickname' => 'Jane',
                'account_password' => $secret,
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('full_name');

        $oldInput = session('_old_input');

        $this->assertIsArray($oldInput);
        $this->assertSame('Jane', $oldInput['nickname'] ?? null);
        $this->assertArrayNotHasKey('account_password', $oldInput);
        $this->assertStringNotContainsString($secret, (string) json_encode(session()->all()));
        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_failed_team_submit_never_flashes_password_answers(): void
    {
        $leader = $this->member();
        $event  = $this->openEvent();
        $form   = $this->teamForm($event, 2);
        $this->textField($form);
        $this->passwordField($form);

        $secret = 'p@ssw0rd-'.uniqid();

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Team Leader',
                'account_password' => $secret,
                'team_member_emails' => ['nobody-'.uniqid().'@example.com'],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails.0');

        $oldInput = session('_old_input');

        $this->assertIsArray($oldInput);
        $this->assertSame('Team Leader', $oldInput['full_name'] ?? null);
        $this->assertArrayNotHasKey('account_password', $oldInput);
        $this->assertStringNotContainsString($secret, (string) json_encode(session()->all()));
        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_failed_bundle_submit_never_flashes_slot_password_answers(): void
    {
        $leader = $this->member();
        $event  = $this->openEvent();
        $form   = $this->bundleForm($event, 2);
        $this->duplicatableTextField($form);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => 'account_password',
            'label'      => 'Account Password',
            'order'      => 2,
            'metadata'   => ['type' => 'password', 'rules' => ['required' => true], 'duplicatable' => true],
        ]);

        $secret = 'slot-secret-'.uniqid();
        $slotSecret = $secret.'-slot';

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'nickname' => 'Lead',
                'account_password' => $secret,
                'bundle__account_password__0' => $slotSecret,
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors(['full_name', 'bundle__full_name__0']);

        $oldInput = session('_old_input');
        $sessionPayload = (string) json_encode(session()->all());

        $this->assertIsArray($oldInput);
        $this->assertSame('Lead', $oldInput['nickname'] ?? null);
        $this->assertArrayNotHasKey('account_password', $oldInput);
        $this->assertArrayNotHasKey('bundle__account_password__0', $oldInput);
        $this->assertStringNotContainsString($secret, $sessionPayload);
        $this->assertStringNotContainsString($slotSecret, $sessionPayload);
        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_non_owner_organizer_success_redirect_lands_on_an_allowed_page(): void
    {
        $owner = $this->admin();
        $submitter = $this->admin();
        $event = $this->openEvent(['created_by' => $owner->id]);
        $form  = $this->openForm($event);
        $this->textField($form);

        $response = $this->actingAs($submitter)
            ->post($this->submitPath($event, $form), ['full_name' => 'Jane Doe']);

        $expected = route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form]);
        $response->assertRedirect($expected);
        $this->assertSame($expected, $response->headers->get('Location'));

        $this->actingAs($submitter)
            ->get($response->headers->get('Location'))
            ->assertOk();
    }

    public function test_display_only_fields_are_not_stored_in_answers(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        foreach (['heading', 'paragraph', 'divider'] as $index => $builderType) {
            FormField::factory()->create([
                'form_id'    => $form->id,
                'input_type' => 'input',
                'name'       => "{$builderType}_block",
                'label'      => ucfirst($builderType),
                'order'      => $index + 2,
                'metadata'   => ['builderType' => $builderType],
            ]);
        }

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'fileUpload',
            'name'       => 'banner_block',
            'label'      => 'Banner',
            'order'      => 2,
            'metadata'   => ['builderType' => 'banner'],
        ]);

        $this->textField($form);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['full_name' => 'Only Real Field'])
            ->assertRedirect($this->submitSuccessRedirect($event, $form));

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame(['full_name'], array_keys($answer->answers));
    }
}

<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Jobs\SendRegistrationConfirmationJob;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * BE_P3_002 characterization for the HTTP boundary of form submission.
 *
 * These assertions intentionally describe the existing contract before the
 * validation/mapping seam is extracted from the controller.
 */
final class FormSubmissionControllerCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_BE_P3_002_single_submission_preserves_success_and_non_owner_redirect(): void
    {
        $owner = $this->admin();
        $submitter = $this->admin();
        $event = $this->openEvent(['created_by' => $owner->id]);
        $form = $this->openForm($event);
        $this->textField($form);

        $response = $this->actingAs($submitter)->post($this->submitPath($event, $form), [
            'full_name' => 'Single Participant',
        ]);

        $response->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form]));
        $this->assertDatabaseCount('form_answers', 1);
    }

    public function test_BE_P3_002_other_submission_does_not_reserve_quota(): void
    {
        $member = $this->member();
        $event = $this->openEvent(['quota' => 1, 'registered_count' => 0]);
        $form = $this->openForm($event, [
            'metadata' => ['purpose' => 'other'],
        ]);
        $this->textField($form);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['full_name' => 'Other Form Entry'])
            ->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form]));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'registered_count' => 0,
        ]);
    }

    public function test_BE_P3_002_team_submission_dispatches_confirmation_after_commit(): void
    {
        Queue::fake();
        $leader = $this->memberWithEmail('be-p3-002-leader-'.uniqid().'@example.com');
        $teammate = $this->memberWithEmail('be-p3-002-member-'.uniqid().'@example.com');
        $event = $this->openEvent();
        $form = $this->openForm($event, [
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
            ->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form]));

        $this->assertDatabaseCount('form_answers', 2);
        Queue::assertPushedTimes(SendRegistrationConfirmationJob::class, 1);
        Queue::assertPushed(
            SendRegistrationConfirmationJob::class,
            static fn (SendRegistrationConfirmationJob $job): bool => $job->afterCommit === true,
        );
    }

    public function test_BE_P3_002_bundle_submission_maps_leader_and_guest_answers(): void
    {
        Queue::fake();
        $leader = $this->member();
        $guestEmail = 'be-p3-002-guest-'.uniqid().'@example.com';
        $event = $this->openEvent();
        $form = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size' => 2,
            ],
        ]);
        $this->duplicatableTextField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Bundle Leader',
                'bundle__full_name__0' => 'Bundle Guest',
                'team_member_emails' => [$guestEmail],
            ])
            ->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form]));

        $this->assertDatabaseCount('form_answers', 2);
        $this->assertDatabaseHas('form_answers', [
            'form_id' => $form->id,
            'invited_email' => mb_strtolower($guestEmail),
        ]);
    }

    public function test_BE_P3_002_blank_or_space_only_member_email_is_validation_feedback(): void
    {
        $leader = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Leader',
                'team_member_emails' => ['   '],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails.0');

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_BE_P3_002_scalar_member_email_payload_is_validation_feedback_not_500(): void
    {
        $leader = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'team',
                'team_size' => 2,
            ],
        ]);
        $this->textField($form);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Leader',
                'team_member_emails' => 'not-an-array@example.com',
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails');

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_BE_P3_002_scalar_answer_payload_is_validation_feedback_not_500(): void
    {
        $member = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event);
        $this->textField($form);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [
                'full_name' => ['not', 'scalar'],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('full_name');

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_BE_P3_002_duplicate_submission_and_quota_conflict_write_no_rows(): void
    {
        $member = $this->member();
        $event = $this->openEvent(['quota' => 1, 'registered_count' => 1]);
        $form = $this->openForm($event);
        $this->textField($form);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => ['full_name' => 'Existing'],
        ]);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['full_name' => 'Duplicate'])
            ->assertRedirect($this->fillPath($event, $form));

        $this->assertDatabaseCount('form_answers', 1);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'registered_count' => 1,
        ]);
    }

    public function test_BE_P3_002_quota_conflict_writes_no_submission(): void
    {
        $member = $this->member();
        $event = $this->openEvent(['quota' => 1, 'registered_count' => 1]);
        $form = $this->openForm($event);
        $this->textField($form);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['full_name' => 'Quota Conflict'])
            ->assertRedirect($this->fillPath($event, $form));

        $this->assertDatabaseCount('form_answers', 0);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'registered_count' => 1,
        ]);
    }

    public function test_BE_P3_002_bundle_duplicate_guest_email_writes_no_submission(): void
    {
        $leader = $this->member();
        $guestEmail = 'be-p3-002-existing-'.uniqid().'@example.com';
        $event = $this->openEvent();
        $form = $this->openForm($event, [
            'metadata' => [
                'registration_mode' => 'bundle',
                'max_team_size' => 2,
            ],
        ]);
        $this->duplicatableTextField($form);
        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => $guestEmail,
            'answers' => ['full_name' => 'Existing Guest'],
        ]);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Bundle Leader',
                'bundle__full_name__0' => 'Bundle Guest',
                'team_member_emails' => [$guestEmail],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('team_member_emails.0');

        $this->assertDatabaseCount('form_answers', 1);
    }

    public function test_BE_P3_002_validation_old_input_redacts_secret_answer(): void
    {
        $member = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event);
        $this->textField($form);
        $this->passwordField($form);
        $secret = 'be-p3-002-secret-'.uniqid();

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [
                'account_password' => $secret,
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('full_name');

        $oldInput = session('_old_input');

        $this->assertIsArray($oldInput);
        $this->assertArrayNotHasKey('account_password', $oldInput);
        $this->assertStringNotContainsString($secret, (string) json_encode(session()->all()));
    }

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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function openEvent(array $overrides = []): Event
    {
        return Event::factory()->create(array_merge([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
            'quota' => 100,
            'registered_count' => 0,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function openForm(Event $event, array $overrides = []): Form
    {
        return Form::factory()->create(array_merge([
            'event_id' => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ], $overrides));
    }

    private function textField(Form $form): FormField
    {
        return FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'full_name',
            'label' => 'Full Name',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => ['required' => true]],
        ]);
    }

    private function duplicatableTextField(Form $form): FormField
    {
        return FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'full_name',
            'label' => 'Full Name',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => ['required' => true], 'duplicatable' => true],
        ]);
    }

    private function passwordField(Form $form): FormField
    {
        return FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'account_password',
            'label' => 'Account Password',
            'order' => 2,
            'metadata' => ['type' => 'password', 'rules' => ['required' => true]],
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
}

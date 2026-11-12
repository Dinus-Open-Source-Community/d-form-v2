<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Team invitation confirmation must reuse the submission answer mapper:
 * appendable fields are merged into the stored answers, an existing upload path
 * survives when no new file is submitted, and scalar fields never accept arrays.
 */
class TeamInvitationAnswerMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
    }

    private function openEvent(): Event
    {
        return Event::factory()->create([
            'status'             => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end'   => now()->addDays(30),
            'quota'              => 100,
            'registered_count'   => 0,
        ]);
    }

    private function teamForm(Event $event): Form
    {
        return Form::factory()->create([
            'event_id'    => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at'   => now()->addDays(30),
            'metadata'    => [
                'registration_mode' => 'team',
                'team_size'         => 2,
            ],
        ]);
    }

    private function appendableTextField(Form $form, string $name = 'bio'): FormField
    {
        return FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => $name,
            'label'      => 'Bio',
            'order'      => 1,
            'is_append'  => true,
            'metadata'   => ['type' => 'text', 'rules' => ['required' => true]],
        ]);
    }

    private function appendablePasswordField(Form $form, string $name = 'account_password'): FormField
    {
        return FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => $name,
            'label'      => 'Account Password',
            'order'      => 3,
            'is_append'  => true,
            'metadata'   => ['type' => 'password', 'rules' => ['required' => true]],
        ]);
    }

    private function appendableFileField(Form $form, string $name = 'portfolio'): FormField
    {
        return FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'fileUpload',
            'name'       => $name,
            'label'      => 'Portfolio',
            'order'      => 2,
            'is_append'  => true,
            'metadata'   => ['rules' => ['required' => true, 'mimes' => 'pdf', 'max_size' => 2048]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array{0: User, 1: FormAnswer, 2: string}
     */
    private function pendingInvitation(Form $form, array $answers): array
    {
        $leaderUser = User::factory()->create();
        $member = User::factory()->create();

        $leader = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $leaderUser->id,
            'registration_role' => RegistrationRole::Leader,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
        ]);

        $token = 'invitation-'.uniqid();

        $row = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'leader_form_answer_id' => $leader->id,
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Pending,
            'invitation_token' => $token,
            'invitation_expired_at' => now()->addDays(7),
            'answers' => $answers,
        ]);

        return [$member, $row, $token];
    }

    private function updatePath(string $token): string
    {
        return route('dashboard.user.team-invitations.update', ['token' => $token], false);
    }

    public function test_confirmation_without_new_upload_keeps_the_stored_file_path(): void
    {
        $event = $this->openEvent();
        $form = $this->teamForm($event);
        $this->appendableFileField($form);

        $storedPath = 'form-uploads/'.$form->id.'/old-portfolio.pdf';

        [$member, $row, $token] = $this->pendingInvitation($form, ['portfolio' => $storedPath]);

        $this->actingAs($member)
            ->post($this->updatePath($token), ['invitation_decision' => 'accept'])
            ->assertRedirect(route('dashboard.user.events.show', ['event_segment' => $event->slug], false));

        $row->refresh();

        $this->assertSame(MemberConfirmationStatus::Accepted, $row->member_confirmation_status);
        $this->assertSame($storedPath, $row->answers['portfolio']);
    }

    public function test_confirmation_with_new_upload_replaces_the_stored_file_path(): void
    {
        $event = $this->openEvent();
        $form = $this->teamForm($event);
        $this->appendableFileField($form);

        $storedPath = 'form-uploads/'.$form->id.'/old-portfolio.pdf';

        [$member, $row, $token] = $this->pendingInvitation($form, ['portfolio' => $storedPath]);

        $upload = UploadedFile::fake()->createWithContent(
            'portfolio.pdf',
            "%PDF-1.7\n".str_repeat('x', 100)."\n%%EOF",
        );

        $this->actingAs($member)
            ->post($this->updatePath($token), [
                'invitation_decision' => 'accept',
                'portfolio' => $upload,
            ])
            ->assertRedirect(route('dashboard.user.events.show', ['event_segment' => $event->slug], false));

        $row->refresh();
        $newPath = $row->answers['portfolio'];

        $this->assertIsString($newPath);
        $this->assertNotSame($storedPath, $newPath);
        $this->assertStringStartsWith('form-uploads/'.$form->id.'/', $newPath);
    }

    public function test_failed_confirmation_never_flashes_password_answers(): void
    {
        $event = $this->openEvent();
        $form = $this->teamForm($event);
        $this->appendableTextField($form, 'bio');
        $this->appendablePasswordField($form);

        [$member, $row, $token] = $this->pendingInvitation($form, ['bio' => 'Stored bio']);

        $secret = 'invite-secret-'.uniqid();
        $confirmationSecret = $secret.'-confirm';

        $this->actingAs($member)
            ->post($this->updatePath($token), [
                'invitation_decision' => 'accept',
                'account_password' => $secret,
                'account_password_confirmation' => $confirmationSecret,
                'password' => $secret,
            ])
            ->assertRedirect(route('dashboard.user.team-invitations.show', ['token' => $token], false))
            ->assertSessionHasErrors('bio');

        $oldInput = session('_old_input');
        $sessionPayload = (string) json_encode(session()->all());

        $this->assertIsArray($oldInput);
        $this->assertSame('accept', $oldInput['invitation_decision'] ?? null);
        $this->assertArrayNotHasKey('account_password', $oldInput);
        $this->assertArrayNotHasKey('account_password_confirmation', $oldInput);
        $this->assertArrayNotHasKey('password', $oldInput);
        $this->assertStringNotContainsString($secret, $sessionPayload);
        $this->assertStringNotContainsString($confirmationSecret, $sessionPayload);

        $row->refresh();
        $this->assertSame(MemberConfirmationStatus::Pending, $row->member_confirmation_status);
        $this->assertSame('Stored bio', $row->answers['bio']);
    }

    public function test_appendable_scalar_field_rejects_payload_arrays_without_writing_answers(): void
    {
        $event = $this->openEvent();
        $form = $this->teamForm($event);
        $this->appendableTextField($form, 'bio');

        [$member, $row, $token] = $this->pendingInvitation($form, ['bio' => 'Stored bio']);

        $this->actingAs($member)
            ->post($this->updatePath($token), [
                'invitation_decision' => 'accept',
                'bio' => ['Injected', 'Array'],
            ])
            ->assertRedirect(route('dashboard.user.team-invitations.show', ['token' => $token], false))
            ->assertSessionHasErrors('bio');

        $row->refresh();

        $this->assertSame(MemberConfirmationStatus::Pending, $row->member_confirmation_status);
        $this->assertSame('Stored bio', $row->answers['bio']);
    }
}

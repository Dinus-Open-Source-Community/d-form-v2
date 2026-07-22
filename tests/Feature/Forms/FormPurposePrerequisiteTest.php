<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAccessStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\FormPurpose;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormPurposePrerequisiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

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

    private function registrationForm(Event $event, array $overrides = []): Form
    {
        return Form::factory()->create(array_merge([
            'event_id' => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
            'metadata' => [
                'purpose' => FormPurpose::Registration->value,
                'registration_mode' => 'single',
                'requires_form_id' => null,
            ],
        ], $overrides));
    }

    private function otherForm(Event $event, ?string $requiresFormId = null, array $overrides = []): Form
    {
        return Form::factory()->create(array_merge([
            'event_id' => $event->id,
            'visible_for' => [EventFormVisibility::Participant->value],
            'closed_at' => now()->addDays(30),
            'metadata' => [
                'purpose' => FormPurpose::Other->value,
                'registration_mode' => null,
                'requires_form_id' => $requiresFormId,
            ],
        ], $overrides));
    }

    private function textField(Form $form, string $name = 'full_name'): FormField
    {
        return FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => $name,
            'label' => 'Full Name',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => ['required' => true]],
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

    private function reviewPath(Event $event, Form $form, FormAnswer $answer): string
    {
        return route('dashboard.events.forms.submissions.review', [
            'event' => $event,
            'form' => $form,
            'formAnswer' => $answer,
        ], false);
    }

    private function submitSuccessRedirect(Event $event, User $user, Form $form): string
    {
        if ($user->can('events.view')) {
            return route('dashboard.events.show', $event);
        }

        return route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form]);
    }

    public function test_other_form_blocked_until_prerequisite_accepted(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $event = $this->openEvent();
        $registration = $this->registrationForm($event);
        $feedback = $this->otherForm($event, $registration->id);
        $this->textField($registration);
        $this->textField($feedback, 'feedback');

        $this->actingAs($member)
            ->get($this->fillPath($event, $feedback))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('accessStatus', FormAccessStatus::PrerequisiteNotMet->value)
            );

        $this->actingAs($member)
            ->post($this->submitPath($event, $registration), ['full_name' => 'Peserta'])
            ->assertRedirect($this->submitSuccessRedirect($event, $member, $registration));

        $answer = FormAnswer::query()
            ->where('form_id', $registration->id)
            ->where('user_id', $member->id)
            ->first();
        $this->assertNotNull($answer);

        $this->actingAs($member)
            ->get($this->fillPath($event, $feedback))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('accessStatus', FormAccessStatus::PrerequisiteNotMet->value)
            );

        $this->actingAs($admin)
            ->patchJson($this->reviewPath($event, $registration, $answer), [
                'review_status' => FormAnswerReviewStatus::Accepted->value,
            ])
            ->assertOk();

        $this->actingAs($member)
            ->get($this->fillPath($event, $feedback))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('accessStatus', FormAccessStatus::Allowed->value)
            );
    }

    public function test_other_form_submit_does_not_increment_registered_count(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $event = $this->openEvent(['registered_count' => 0]);
        $registration = $this->registrationForm($event);
        $feedback = $this->otherForm($event, $registration->id);
        $this->textField($registration);
        $this->textField($feedback, 'feedback');

        $this->actingAs($member)
            ->post($this->submitPath($event, $registration), ['full_name' => 'Peserta'])
            ->assertRedirect($this->submitSuccessRedirect($event, $member, $registration));

        $event->refresh();
        $this->assertSame(1, $event->registered_count);

        $answer = FormAnswer::query()
            ->where('form_id', $registration->id)
            ->where('user_id', $member->id)
            ->first();

        $this->actingAs($admin)
            ->patchJson($this->reviewPath($event, $registration, $answer), [
                'review_status' => FormAnswerReviewStatus::Accepted->value,
            ])
            ->assertOk();

        $this->actingAs($member)
            ->post($this->submitPath($event, $feedback), ['feedback' => 'Bagus'])
            ->assertRedirect($this->submitSuccessRedirect($event, $member, $feedback));

        $event->refresh();
        $this->assertSame(1, $event->registered_count);
        $this->assertDatabaseHas('form_answers', [
            'form_id' => $feedback->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_second_other_form_allowed_after_registration_accepted(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $event = $this->openEvent();
        $registration = $this->registrationForm($event);
        $surveyA = $this->otherForm($event, $registration->id, ['title' => 'Survey A']);
        $surveyB = $this->otherForm($event, $registration->id, ['title' => 'Survey B']);
        $this->textField($registration);
        $this->textField($surveyA, 'a');
        $this->textField($surveyB, 'b');

        $this->actingAs($member)
            ->post($this->submitPath($event, $registration), ['full_name' => 'Peserta'])
            ->assertRedirect();

        $answer = FormAnswer::query()
            ->where('form_id', $registration->id)
            ->where('user_id', $member->id)
            ->first();

        $this->actingAs($admin)
            ->patchJson($this->reviewPath($event, $registration, $answer), [
                'review_status' => FormAnswerReviewStatus::Accepted->value,
            ])
            ->assertOk();

        $this->actingAs($member)
            ->post($this->submitPath($event, $surveyA), ['a' => 'One'])
            ->assertRedirect($this->submitSuccessRedirect($event, $member, $surveyA));

        $this->actingAs($member)
            ->get($this->fillPath($event, $surveyB))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('accessStatus', FormAccessStatus::Allowed->value)
            );
    }

    public function test_two_registration_forms_still_enforce_one_choice(): void
    {
        $member = $this->member();
        $event = $this->openEvent();
        $formA = $this->registrationForm($event, ['title' => 'Reg A']);
        $formB = $this->registrationForm($event, ['title' => 'Reg B']);
        $this->textField($formA);
        $this->textField($formB);

        $this->actingAs($member)
            ->post($this->submitPath($event, $formA), ['full_name' => 'A'])
            ->assertRedirect();

        $this->actingAs($member)
            ->get($this->fillPath($event, $formB))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('accessStatus', FormAccessStatus::EventFormAlreadyChosen->value)
            );
    }

    public function test_other_form_ignores_registration_window_and_quota(): void
    {
        $member = $this->member();
        $event = $this->openEvent([
            'registration_end' => now()->subDay(),
            'quota' => 1,
            'registered_count' => 1,
        ]);
        $registration = $this->registrationForm($event, [
            'closed_at' => now()->addDays(30),
        ]);
        $feedback = $this->otherForm($event, $registration->id, [
            'closed_at' => now()->addDays(30),
        ]);
        $this->textField($feedback, 'feedback');

        FormAnswer::query()->create([
            'form_id' => $registration->id,
            'user_id' => $member->id,
            'answers' => ['full_name' => 'Already in'],
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        $this->actingAs($member)
            ->get($this->fillPath($event, $feedback))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('accessStatus', FormAccessStatus::Allowed->value)
            );

        $this->actingAs($member)
            ->post($this->submitPath($event, $feedback), ['feedback' => 'Ok'])
            ->assertRedirect($this->submitSuccessRedirect($event, $member, $feedback));

        $event->refresh();
        $this->assertSame(1, $event->registered_count);
    }

    public function test_registration_picker_excludes_other_purpose_forms(): void
    {
        $member = $this->member();
        $event = $this->openEvent();
        $registration = $this->registrationForm($event, ['title' => 'Registration Only']);
        $feedback = $this->otherForm($event, $registration->id, ['title' => 'Feedback Hidden']);

        $this->actingAs($member)
            ->get(route('dashboard.user.events.register', ['event_segment' => $event->slug], false))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                ->component('Dashboard/User/EventRegistrationPickForm')
                ->has('forms', 1)
                ->where('forms.0.id', $registration->id)
                ->where('forms.0.title', 'Registration Only')
            );

        $this->assertNotSame($feedback->id, $registration->id);
    }

    public function test_event_detail_includes_participant_forms(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $event = $this->openEvent();
        $registration = $this->registrationForm($event);
        $feedback = $this->otherForm($event, $registration->id, ['title' => 'Feedback Survey']);
        $this->textField($registration);

        $this->actingAs($member)
            ->post($this->submitPath($event, $registration), ['full_name' => 'Peserta'])
            ->assertRedirect();

        $answer = FormAnswer::query()
            ->where('form_id', $registration->id)
            ->where('user_id', $member->id)
            ->first();

        $this->actingAs($admin)
            ->patchJson($this->reviewPath($event, $registration, $answer), [
                'review_status' => FormAnswerReviewStatus::Accepted->value,
            ])
            ->assertOk();

        $this->actingAs($member)
            ->get(route('dashboard.user.events.show', ['event_segment' => $event->slug], false))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                ->component('Dashboard/User/EventDetail')
                ->where('isRegistered', true)
                ->has('participantForms', 1)
                ->where('participantForms.0.id', $feedback->id)
                ->where('participantForms.0.can_start', true)
            );
    }
}

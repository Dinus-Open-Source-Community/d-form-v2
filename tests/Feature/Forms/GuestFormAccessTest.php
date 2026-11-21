<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Form\GuestFormToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class GuestFormAccessTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $metadata = [], array $attributes = []): array
    {
        $event = Event::factory()->create(['status' => EventStatus::Published]);
        $form = Form::factory()->create(array_merge([
            'event_id' => $event->id,
            'status' => FormStatus::Published,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => null,
            'metadata' => array_merge(['purpose' => 'other', 'allow_guest_submissions' => true], $metadata),
        ], $attributes));
        return [$event, $form];
    }

    public function test_guest_can_fill_through_the_shared_participant_url(): void
    {
        [$event, $form] = $this->form();

        $this->get(route('dashboard.events.forms.fill', compact('event', 'form')))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Dashboard/Events/Forms/Fill'));
    }

    public function test_guest_setting_allows_participant_visible_form_with_prerequisite(): void
    {
        [$event, $form] = $this->form(
            ['allow_guest_submissions' => true, 'requires_form_id' => '01a1fd10-1ee2-7937-bf7b-7027242839e6'],
            ['visible_for' => [EventFormVisibility::Participant->value], 'closed_at' => now()->addDay()],
        );

        $this->get(route('dashboard.events.forms.fill', compact('event', 'form')))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Dashboard/Events/Forms/Fill'));
    }

    public function test_ineligible_guest_is_redirected_to_login_on_shared_participant_url(): void
    {
        [$event, $form] = $this->form(['allow_guest_submissions' => false]);
        $participantPath = route('dashboard.events.forms.fill', compact('event', 'form'), false);

        $this->get($participantPath)
            ->assertRedirect(route('auth.login'));
    }

    public function test_eligible_fill_sets_cookie_and_marker(): void
    {
        [$event, $form] = $this->form();
        $response = $this->get(route('dashboard.events.forms.fill', compact('event', 'form')));
        $response->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('submitUrl', route('dashboard.forms.submission', ['event' => $event, 'form' => $form]))
                ->where('registrationMode', 'single')
                ->where('memberSlots', 0)
                ->where('pendingInvitationUrl', null));
        $response->assertCookie(GuestFormToken::COOKIE_NAME);
        $this->assertTrue(session('guest_form_access.'.$form->id));
    }

    public function test_fill_rejects_form_from_different_event_without_cookie(): void
    {
        [$event, $form] = $this->form();
        $otherEvent = Event::factory()->create(['status' => EventStatus::Published]);

        $this->get(route('dashboard.events.forms.fill', ['event' => $otherEvent, 'form' => $form]))
            ->assertNotFound()
            ->assertCookieMissing(GuestFormToken::COOKIE_NAME);
    }

    public function test_ineligible_fill_fails_without_cookie_or_marker(): void
    {
        foreach ([
            ['allow_guest_submissions' => false],
            ['purpose' => 'registration'],
        ] as $metadata) {
            [$event, $form] = $this->form($metadata);
            $response = $this->get(route('dashboard.events.forms.fill', compact('event', 'form')));
            $response->assertRedirect(route('auth.login'))->assertCookieMissing(GuestFormToken::COOKIE_NAME);
            $this->assertFalse(session()->has('guest_form_access.'.$form->id));
        }
    }

    public function test_post_and_success_require_marker_and_cookie(): void
    {
        [$event, $form] = $this->form();
        $this->assertTrue(true);
        $this->get(route('dashboard.events.forms.submitted', compact('event', 'form')))->assertNotFound();
    }

    public function test_success_rejects_malformed_cookie_with_marker(): void
    {
        [$event, $form] = $this->form();

        $this->withSession(['guest_form_access.'.$form->id => true])
            ->withUnencryptedCookie(GuestFormToken::COOKIE_NAME, 'malformed-token')
            ->get(route('dashboard.events.forms.submitted', compact('event', 'form')))
            ->assertNotFound();
    }

    public function test_success_requires_active_submission_for_current_cookie(): void
    {
        [$event, $form] = $this->form(['allow_multiple_submissions' => true, 'max_submissions' => 2]);
        $token = GuestFormToken::issue();
        $this->withSession(['guest_form_access.'.$form->id => true]);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => null, 'guest_token_hash' => GuestFormToken::hash($token), 'is_active' => 1]);
        $this->withCookie(GuestFormToken::COOKIE_NAME, $token)
            ->get(route('dashboard.events.forms.submitted', compact('event', 'form')))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('fillUrl', route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false))
                ->where('canSubmitAgain', true));
    }

    public function test_guest_final_submission_disables_repeat_action(): void
    {
        [$event, $form] = $this->form(['allow_multiple_submissions' => true, 'max_submissions' => 2]);
        $token = GuestFormToken::issue();
        $this->withSession(['guest_form_access.'.$form->id => true]);
        FormAnswer::factory()->count(2)->create([
            'form_id' => $form->id,
            'user_id' => null,
            'guest_token_hash' => GuestFormToken::hash($token),
            'is_active' => 1,
        ]);

        $this->withCookie(GuestFormToken::COOKIE_NAME, $token)
            ->get(route('dashboard.events.forms.submitted', compact('event', 'form')))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('canSubmitAgain', false));
    }

    public function test_guest_owned_success_remains_available_after_form_closes(): void
    {
        [$event, $form] = $this->form();
        $form->update(['closed_at' => now()->subMinute()]);
        $token = GuestFormToken::issue();
        $this->withSession(['guest_form_access.'.$form->id => true]);
        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'guest_token_hash' => GuestFormToken::hash($token),
            'is_active' => 1,
        ]);

        $this->withCookie(GuestFormToken::COOKIE_NAME, $token)
            ->get(route('dashboard.events.forms.submitted', compact('event', 'form')))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Dashboard/Events/Forms/SubmitSuccess')
                ->where('canSubmitAgain', false));
    }

    public function test_guest_success_hides_repeat_action_when_guest_access_is_disabled(): void
    {
        [$event, $form] = $this->form(['allow_multiple_submissions' => true, 'max_submissions' => 2]);
        $token = GuestFormToken::issue();
        $this->withSession(['guest_form_access.'.$form->id => true]);
        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'guest_token_hash' => GuestFormToken::hash($token),
            'is_active' => 1,
        ]);
        $form->update(['metadata' => ['purpose' => 'other', 'allow_guest_submissions' => false, 'allow_multiple_submissions' => true, 'max_submissions' => 2]]);

        $this->withCookie(GuestFormToken::COOKIE_NAME, $token)
            ->get(route('dashboard.events.forms.submitted', compact('event', 'form')))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('canSubmitAgain', false));
    }
}

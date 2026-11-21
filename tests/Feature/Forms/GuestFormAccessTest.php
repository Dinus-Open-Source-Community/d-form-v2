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

    public function test_eligible_fill_sets_cookie_and_marker(): void
    {
        [$event, $form] = $this->form();
        $response = $this->get(route('dashboard.public.events.forms.fill', compact('event', 'form')));
        $response->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('submitUrl', route('dashboard.public.events.forms.submission', ['event' => $event, 'form' => $form]))
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

        $this->get(route('dashboard.public.events.forms.fill', ['event' => $otherEvent, 'form' => $form]))
            ->assertNotFound()
            ->assertCookieMissing(GuestFormToken::COOKIE_NAME);
    }

    public function test_ineligible_fill_fails_without_cookie_or_marker(): void
    {
        foreach ([
            ['allow_guest_submissions' => false],
            ['purpose' => 'registration'],
            ['requires_form_id' => '00000000-0000-0000-0000-000000000001'],
        ] as $metadata) {
            [$event, $form] = $this->form($metadata);
            $response = $this->get(route('dashboard.public.events.forms.fill', compact('event', 'form')));
            $response->assertNotFound()->assertCookieMissing(GuestFormToken::COOKIE_NAME);
            $this->assertFalse(session()->has('guest_form_access.'.$form->id));
        }
    }

    public function test_post_and_success_require_marker_and_cookie(): void
    {
        [$event, $form] = $this->form();
        $this->assertTrue(true);
        $this->get(route('dashboard.public.events.forms.submitted', compact('event', 'form')))->assertNotFound();
    }

    public function test_success_rejects_malformed_cookie_with_marker(): void
    {
        [$event, $form] = $this->form();

        $this->withSession(['guest_form_access.'.$form->id => true])
            ->withUnencryptedCookie(GuestFormToken::COOKIE_NAME, 'malformed-token')
            ->get(route('dashboard.public.events.forms.submitted', compact('event', 'form')))
            ->assertNotFound();
    }

    public function test_success_requires_active_submission_for_current_cookie(): void
    {
        [$event, $form] = $this->form();
        $token = GuestFormToken::issue();
        $this->withSession(['guest_form_access.'.$form->id => true]);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => null, 'guest_token_hash' => GuestFormToken::hash($token), 'is_active' => 1]);
        $this->withCookie(GuestFormToken::COOKIE_NAME, $token)->get(route('dashboard.public.events.forms.submitted', compact('event', 'form')))->assertOk();
    }
}

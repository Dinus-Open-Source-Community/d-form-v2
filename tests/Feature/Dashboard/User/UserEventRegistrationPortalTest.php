<?php

namespace Tests\Feature\Dashboard\User;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Mail\RegistrationAcceptedMail;
use App\Mail\RegistrationConfirmationMail;
use App\Mail\RegistrationRejectedMail;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Support\RegistrationPortalLinks;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEventRegistrationPortalTest extends TestCase
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

    /** Published event with sane registration window. */
    private function publishedEvent(array $overrides = []): Event
    {
        return Event::factory()->create(array_merge([
            'status' => EventStatus::Published,
            'slug' => 'test-event-portal',
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ], $overrides));
    }

    private function primaryForm(Event $event): Form
    {
        return Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Alpha registration form',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);
    }

    private function registrationUrl(Event $event, bool $useUuid = false): string
    {
        return route('dashboard.user.events.registration', [
            'event_segment' => $useUuid ? $event->getKey() : $event->slug,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $event = $this->publishedEvent();

        $this->get($this->registrationUrl($event))
            ->assertRedirect(route('auth.login'));
    }

    public function test_unknown_slug_returns_404(): void
    {
        $member = $this->member();

        $this->actingAs($member)->get(route('dashboard.user.events.registration', [
            'event_segment' => 'no-such-slug-exists',
        ]))->assertNotFound();
    }

    public function test_unpublished_event_returns_404_even_when_uuid_matches(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent(['status' => EventStatus::Draft]);

        $this->actingAs($member)->get($this->registrationUrl($event, useUuid: true))
            ->assertNotFound();
    }

    public function test_non_registrant_returns_404(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent();
        $this->primaryForm($event);

        $this->actingAs($member)->get($this->registrationUrl($event))
            ->assertNotFound();
    }

    public function test_registration_answer_must_be_for_primary_form_by_title(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent();

        $this->primaryForm($event);

        $secondaryForm = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Zeta other form',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        FormAnswer::factory()->create([
            'form_id' => $secondaryForm->id,
            'user_id' => $member->id,
            'answers' => [],
        ]);

        $this->actingAs($member)->get($this->registrationUrl($event))
            ->assertNotFound();
    }

    public function test_slug_and_uuid_segments_resolve_for_registrant(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent();
        $form = $this->primaryForm($event);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => [],
        ]);

        foreach ([false, true] as $useUuid) {
            $this->actingAs($member)->get($this->registrationUrl($event, $useUuid))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Dashboard/User/EventRegistration')
                    ->where('event.slug', $event->slug)
                    ->where('registration.review_status', 'pending')
                    ->where('registration.registration_code', null)
                    ->where('registration.qr_base64', fn ($v) => $v === null)
                );
        }
    }

    public function test_accepted_registrant_receives_qr_and_registration_code(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent();
        $form = $this->primaryForm($event);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => [],
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'CHK-999',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($member)->get($this->registrationUrl($event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/User/EventRegistration')
                ->where('registration.review_status', 'accepted')
                ->where('registration.registration_code', 'CHK-999')
                ->where('registration.qr_base64', fn ($b64) => is_string($b64) && strlen($b64) > 50)
            );
    }

    public function test_rejected_registrant_has_no_qr(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent();
        $form = $this->primaryForm($event);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => [],
            'review_status' => FormAnswerReviewStatus::Rejected,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($member)->get($this->registrationUrl($event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/User/EventRegistration')
                ->where('registration.review_status', 'rejected')
                ->where('registration.registration_code', null)
                ->where('registration.qr_base64', fn ($v) => $v === null)
            );
    }

    public function test_registration_mailables_contain_portal_url(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent(['slug' => 'mail-slug-demo']);
        $form = $this->primaryForm($event);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'answers' => [],
        ]);

        $answer->loadMissing('form.event', 'user');

        $expectedUrl = RegistrationPortalLinks::registrationDetailsUrl($event);

        $confirmation = new RegistrationConfirmationMail($answer, []);
        $this->assertStringContainsString($expectedUrl, $confirmation->render());

        $rejected = new RegistrationRejectedMail($answer);
        $this->assertStringContainsString($expectedUrl, $rejected->render());

        $accepted = new RegistrationAcceptedMail($answer, 'fake-png', 'CODE');
        $this->assertStringContainsString($expectedUrl, $accepted->render());
    }
}

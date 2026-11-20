<?php

namespace Tests\Feature\Dashboard\User;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Mail\RegistrationAcceptedMail;
use App\Mail\RegistrationConfirmationMail;
use App\Mail\RegistrationRejectedMail;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Services\Registration\RegistrationQrPngGenerator;
use App\Services\Registration\FormAnswerRecipientResolver;
use App\Support\RegistrationPortalLinks;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_removed_joined_register_url_returns_404(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent(['slug' => 'removed-register-event']);

        $this->actingAs($member)
            ->get('/joined/'.$event->slug.'/register')
            ->assertNotFound();
    }

    public function test_removed_legacy_register_redirect_returns_404(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent(['slug' => 'removed-legacy-register-event']);

        $this->actingAs($member)
            ->get('/user/dashboard/events/'.$event->slug.'/register')
            ->assertNotFound();
    }

    public function test_event_detail_includes_sorted_registration_form_summaries(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent(['slug' => 'detail-registration-forms-event']);
        $later = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Zeta Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);
        $earlier = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Alpha Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        $this->actingAs($member)
            ->get(route('dashboard.user.events.show', ['event_segment' => $event->slug]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Dashboard/User/EventDetail')
                    ->has('registrationForms', 2)
                    ->where('registrationForms.0.id', $earlier->id)
                    ->where('registrationForms.0.title', 'Alpha Registration')
                    ->where('registrationForms.0.fill_url', route(
                        'dashboard.events.forms.fill',
                        ['event' => $event, 'form' => $earlier],
                        false,
                    ))
                    ->where('registrationForms.0.access_status', 'allowed')
                    ->where('registrationForms.0.can_start', true)
                    ->where('registrationForms.0.requires_form_title', null)
                    ->where('registrationForms.1.id', $later->id)
                    ->where('registrationForms.1.title', 'Zeta Registration')
            );
    }

    public function test_detail_omits_banner_url_when_the_stored_file_is_missing(): void
    {
        Storage::fake('public');
        $member = $this->member();
        $event = $this->publishedEvent([
            'slug' => 'missing-banner-event',
            'banner' => 'events/banners/missing.jpg',
        ]);

        $this->actingAs($member)
            ->get(route('dashboard.user.events.show', ['event_segment' => $event->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('event.banner_url', null));
    }

    public function test_detail_publishes_banner_url_when_the_stored_file_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('events/banners/present.jpg', 'binary');
        $member = $this->member();
        $event = $this->publishedEvent([
            'slug' => 'present-banner-event',
            'banner' => 'events/banners/present.jpg',
        ]);

        $this->actingAs($member)
            ->get(route('dashboard.user.events.show', ['event_segment' => $event->slug]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('event.banner_url', url('/storage/events/banners/present.jpg'))
            );
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

    public function test_registration_resolves_when_answer_belongs_to_any_event_form(): void
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
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Dashboard/User/EventRegistration')
                    ->where('form.id', $secondaryForm->id)
                    ->where('form.title', 'Zeta other form')
                    ->where('registration.registration_role', null)
            );
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
                ->assertInertia(
                    fn ($page) => $page
                    ->component('Dashboard/User/EventRegistration')
                    ->where('event.slug', $event->slug)
                    ->where('form.id', $form->id)
                    ->where('form.title', $form->title)
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
            ->assertInertia(
                fn ($page) => $page
                ->component('Dashboard/User/EventRegistration')
                ->where('registration.review_status', 'accepted')
                ->where('registration.registration_code', 'CHK-999')
                ->where('registration.qr_base64', fn ($b64) => is_string($b64) && strlen($b64) > 50)
            );
    }

    public function test_BE_P3_001_event_portal_preserves_detail_props_and_accepted_qr_data(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent(['slug' => 'detail-accepted-event']);
        $form = $this->primaryForm($event);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'DETAIL-999',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($member)
            ->get(route('dashboard.user.events.show', ['event_segment' => $event->slug]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Dashboard/User/EventDetail')
                    ->where('event.id', $event->id)
                    ->where('isRegistered', true)
                    ->where('pendingTeamInvitationUrl', null)
                    ->where('registrationStatus', 'accepted')
                    ->where('registration_code', 'DETAIL-999')
                    ->where('qr_base64', fn ($value) => is_string($value) && strlen($value) > 50)
                    ->has('participantForms', 0)
            );
    }

    public function test_event_portal_exposes_form_purpose_and_empty_check_in_before_scan(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent(['slug' => 'purpose-checkin-event']);
        $form = $this->primaryForm($event);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'SCAN-1',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($member)
            ->get(route('dashboard.user.events.show', ['event_segment' => $event->slug]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Dashboard/User/EventDetail')
                    ->where('registrationForms.0.purpose', 'registration')
                    ->where('checked_in_at', null)
                    ->where('check_in_form_title', $form->title)
            );
    }

    public function test_event_portal_exposes_check_in_time_after_scan(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent(['slug' => 'scanned-checkin-event']);
        $form = $this->primaryForm($event);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'SCAN-2',
            'reviewed_at' => now(),
        ]);

        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $member->id,
            'scanned_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($member)
            ->get(route('dashboard.user.events.show', ['event_segment' => $event->slug]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Dashboard/User/EventDetail')
                    ->where('checked_in_at', fn ($value) => is_string($value) && $value !== '')
                    ->where('check_in_form_title', $form->title)
            );
    }

    public function test_event_portal_labels_participant_forms_as_other_purpose(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent(['slug' => 'participant-purpose-event']);
        $form = $this->primaryForm($event);
        $participantForm = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Feedback survey',
            'visible_for' => [EventFormVisibility::Participant->value],
            'closed_at' => now()->addDays(30),
            'metadata' => ['purpose' => 'other'],
        ]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($member)
            ->get(route('dashboard.user.events.show', ['event_segment' => $event->slug]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Dashboard/User/EventDetail')
                    ->where('participantForms.0.id', $participantForm->id)
                    ->where('participantForms.0.purpose', 'other')
            );
    }

    public function test_BE_P3_001_event_portal_preserves_pending_invitation_url(): void
    {
        $member = $this->member();
        $event = $this->publishedEvent(['slug' => 'detail-pending-event']);
        $form = $this->primaryForm($event);
        $token = 'detail-pending-'.uniqid();

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Pending,
            'invitation_token' => $token,
        ]);

        $this->actingAs($member)
            ->get(route('dashboard.user.events.show', ['event_segment' => $event->slug]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Dashboard/User/EventDetail')
                    ->where('event.id', $event->id)
                    ->where('isRegistered', false)
                    ->where('pendingTeamInvitationUrl', route(
                        'dashboard.user.team-invitations.show',
                        ['token' => $token],
                        false,
                    ))
                    ->where('registrationStatus', null)
                    ->where('qr_base64', null)
                    ->where('registration_code', null)
                    ->has('participantForms', 0)
            );
    }

    public function test_BE_P3_001_non_owner_can_view_published_event_without_registration_props(): void
    {
        $owner = $this->member();
        $viewer = $this->member();
        $event = $this->publishedEvent(['slug' => 'detail-non-owner-event']);
        $form = $this->primaryForm($event);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'answers' => [],
        ]);

        $this->actingAs($viewer)
            ->get(route('dashboard.user.events.show', ['event_segment' => $event->slug]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Dashboard/User/EventDetail')
                    ->where('event.id', $event->id)
                    ->where('isRegistered', false)
                    ->where('pendingTeamInvitationUrl', null)
                    ->where('registrationStatus', null)
                    ->where('qr_base64', null)
                    ->where('registration_code', null)
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

        // Rejected submissions are excluded from the portal (users may re-register),
        // so the registration details page no longer resolves.
        $this->actingAs($member)->get($this->registrationUrl($event))
            ->assertNotFound();
    }

    public function test_bundle_leader_sees_participant_qrs_on_registration_details(): void
    {
        $leader = $this->member();
        $event = $this->publishedEvent();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Bundle form',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
            'metadata' => ['registration_mode' => 'bundle', 'max_team_size' => 2],
        ]);

        $groupToken = 'BNDL'.uniqid();

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $leader->id,
            'registration_role' => RegistrationRole::Leader,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'group_token' => $groupToken,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'LEAD-001',
            'answers' => ['full_name' => 'Leader Name'],
        ]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'group_token' => $groupToken,
            'invited_email' => 'guest-accepted@example.com',
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'GUEST-001',
            'answers' => ['full_name' => 'Guest Accepted'],
        ]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'group_token' => $groupToken,
            'invited_email' => 'guest-pending@example.com',
            'review_status' => FormAnswerReviewStatus::Pending,
            'answers' => ['full_name' => 'Guest Pending'],
        ]);

        $this->actingAs($leader)->get($this->registrationUrl($event))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Dashboard/User/EventRegistration')
                    ->where('form.registration_mode', 'bundle')
                    ->where('registration.registration_role', 'leader')
                    ->has('bundle_participants', 2)
                    ->where('bundle_participants.0.invited_email', 'guest-accepted@example.com')
                    ->where('bundle_participants.0.review_status', 'accepted')
                    ->where('bundle_participants.0.registration_code', 'GUEST-001')
                    ->where('bundle_participants.0.qr_base64', fn ($b64) => is_string($b64) && strlen($b64) > 50)
                    ->where('bundle_participants.1.invited_email', 'guest-pending@example.com')
                    ->where('bundle_participants.1.review_status', 'pending')
                    ->where('bundle_participants.1.qr_base64', fn ($v) => $v === null)
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

        $qrPng = app(RegistrationQrPngGenerator::class)->pngForSubmission($answer->id);
        $confirmation = new RegistrationConfirmationMail($answer, [], $qrPng);
        $html = $confirmation->render();
        $this->assertStringContainsString($expectedUrl, $html);
        $this->assertStringContainsString('cid:qr-code.png', $html);
        // Blade meng-escape nama; faker bisa menghasilkan apostrof (mis. O'Connell).
        $this->assertStringContainsString(e($member->name), $html);

        $rejected = new RegistrationRejectedMail($answer);
        $this->assertStringContainsString($expectedUrl, $rejected->render());

        $accepted = new RegistrationAcceptedMail($answer, 'fake-png', 'CODE');
        $this->assertStringContainsString($expectedUrl, $accepted->render());
    }

    public function test_recipient_resolver_normalizes_and_rejects_invalid_emails(): void
    {
        $resolver = app(FormAnswerRecipientResolver::class);

        $validGuest = FormAnswer::factory()->make([
            'user_id' => null,
            'invited_email' => '  Guest-Mail@Example.COM ',
        ]);
        $this->assertSame('guest-mail@example.com', $resolver->email($validGuest));
        $this->assertTrue($resolver->isGuest($validGuest));

        $invalidGuest = FormAnswer::factory()->make([
            'user_id' => null,
            'invited_email' => 'not-an-email',
        ]);
        $this->assertNull($resolver->email($invalidGuest));
    }

    public function test_guest_registration_mailables_use_public_event_url_and_recipient_name(): void
    {
        $event = $this->publishedEvent(['slug' => 'guest-mail-event']);
        $form = $this->primaryForm($event);

        $guestAnswer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => 'bundle-guest-mail@example.com',
            'answers' => ['full_name' => 'Guest Participant'],
        ]);

        $guestAnswer->loadMissing('form.event', 'user');

        $publicUrl = RegistrationPortalLinks::publicEventUrl($event);
        $portalUrl = RegistrationPortalLinks::registrationDetailsUrl($event);

        $confirmation = new RegistrationConfirmationMail($guestAnswer, ['Name' => 'Guest Participant']);
        $confirmationHtml = $confirmation->render();
        $this->assertStringContainsString('Guest Participant', $confirmationHtml);
        $this->assertStringContainsString($publicUrl, $confirmationHtml);
        $this->assertStringNotContainsString($portalUrl, $confirmationHtml);
        $this->assertStringNotContainsString((string) $guestAnswer->id, $confirmationHtml);

        $accepted = new RegistrationAcceptedMail($guestAnswer, 'fake-png', 'GUEST-CODE');
        $acceptedHtml = $accepted->render();
        $this->assertStringContainsString('Guest Participant', $acceptedHtml);
        $this->assertStringContainsString('GUEST-CODE', $acceptedHtml);
        $this->assertStringContainsString($publicUrl, $acceptedHtml);
        $this->assertStringNotContainsString($portalUrl, $acceptedHtml);

        $rejected = new RegistrationRejectedMail($guestAnswer);
        $rejectedHtml = $rejected->render();
        $this->assertStringContainsString('Guest Participant', $rejectedHtml);
        $this->assertStringContainsString($publicUrl, $rejectedHtml);
        $this->assertStringNotContainsString($portalUrl, $rejectedHtml);
    }
}

<?php

namespace Tests\Feature\Forms;

use App\Enums\EventStatus;
use App\Enums\RegistrationRole;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class FormUploadPrivacyTest extends TestCase
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

    private function event(User $owner): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
            'quota' => 100,
            'registered_count' => 0,
            'created_by' => $owner->id,
        ]);
    }

    private function form(Event $event): Form
    {
        return Form::factory()->create(['event_id' => $event->id]);
    }

    /**
     * @return array{answer: FormAnswer, path: string, url: string}
     */
    private function answerWithFile(Event $event, Form $form, User $owner, string $disk = 'local'): array
    {
        FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'fileUpload',
            'name' => 'cv',
            'label' => 'CV',
            'order' => 1,
            'metadata' => [],
        ]);

        $path = 'form-uploads/'.$form->id.'/private-cv.pdf';
        Storage::disk($disk)->put($path, '%PDF-1.7 test content');

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'answers' => ['cv' => $path],
        ]);

        return [
            'answer' => $answer,
            'path' => $path,
            'url' => route('dashboard.form-answers.files', [
                'formAnswer' => $answer->id,
                'field' => 'cv',
                'filename' => 'private-cv.pdf',
            ], false),
        ];
    }

    public function test_guest_without_signature_is_forbidden(): void
    {
        Storage::fake('local');

        $owner = $this->member();
        $event = $this->event($owner);
        $form = $this->form($event);
        $fixture = $this->answerWithFile($event, $form, $owner);

        $this->get($fixture['url'])->assertForbidden();

        $this->assertSame($fixture['path'], $fixture['answer']->answers['cv']);
    }

    public function test_unrelated_authenticated_user_is_forbidden(): void
    {
        Storage::fake('local');

        $owner = $this->member();
        $intruder = $this->member();
        $event = $this->event($owner);
        $form = $this->form($event);
        $fixture = $this->answerWithFile($event, $form, $owner);

        $this->actingAs($intruder)->get($fixture['url'])->assertForbidden();
    }

    public function test_owner_can_download_own_upload_through_authenticated_route(): void
    {
        Storage::fake('local');

        $owner = $this->member();
        $event = $this->event($owner);
        $form = $this->form($event);
        $fixture = $this->answerWithFile($event, $form, $owner);

        $response = $this->actingAs($owner)->get($fixture['url']);

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('%PDF-1.7 test content', $response->streamedContent());
    }

    public function test_event_owner_can_download_participant_upload(): void
    {
        Storage::fake('local');

        $owner = $this->member();
        $organizer = User::factory()->create();
        $organizer->assignRole('admin');

        $event = $this->event($organizer);
        $form = $this->form($event);
        $fixture = $this->answerWithFile($event, $form, $owner);

        $this->actingAs($organizer)->get($fixture['url'])->assertOk();
    }

    public function test_team_leader_can_download_member_upload(): void
    {
        Storage::fake('local');

        $leader = $this->member();
        $member = $this->member();
        $event = $this->event($leader);
        $form = $this->form($event);

        $path = 'form-uploads/'.$form->id.'/member-cv.pdf';
        Storage::disk('local')->put($path, '%PDF-1.7 member content');

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $leader->id,
            'registration_role' => RegistrationRole::Leader,
            'group_token' => 'GROUP123',
        ]);

        $memberAnswer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'registration_role' => RegistrationRole::Member,
            'group_token' => 'GROUP123',
            'answers' => ['cv' => $path],
        ]);

        FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'fileUpload',
            'name' => 'cv',
            'label' => 'CV',
            'order' => 1,
            'metadata' => [],
        ]);

        $url = route('dashboard.form-answers.files', [
            'formAnswer' => $memberAnswer->id,
            'field' => 'cv',
            'filename' => 'member-cv.pdf',
        ], false);

        $this->actingAs($leader)->get($url)->assertOk();
    }

    public function test_temporary_signed_url_allows_guest_recipient(): void
    {
        Storage::fake('local');

        $owner = $this->member();
        $event = $this->event($owner);
        $form = $this->form($event);
        $fixture = $this->answerWithFile($event, $form, $owner);

        $signed = URL::temporarySignedRoute(
            'dashboard.form-answers.files',
            now()->addMinutes(10),
            [
                'formAnswer' => $fixture['answer']->id,
                'field' => 'cv',
                'filename' => 'private-cv.pdf',
            ],
        );

        $this->get($signed)->assertOk();
    }

    public function test_tampered_signature_is_rejected(): void
    {
        Storage::fake('local');

        $owner = $this->member();
        $event = $this->event($owner);
        $form = $this->form($event);
        $fixture = $this->answerWithFile($event, $form, $owner);

        $signed = URL::temporarySignedRoute(
            'dashboard.form-answers.files',
            now()->addMinutes(10),
            [
                'formAnswer' => $fixture['answer']->id,
                'field' => 'cv',
                'filename' => 'private-cv.pdf',
            ],
        );

        $this->get($signed.'&signature=deadbeef')->assertForbidden();
    }

    public function test_expired_signature_is_rejected(): void
    {
        Storage::fake('local');

        $owner = $this->member();
        $event = $this->event($owner);
        $form = $this->form($event);
        $fixture = $this->answerWithFile($event, $form, $owner);

        $signed = URL::temporarySignedRoute(
            'dashboard.form-answers.files',
            now()->addMinute(),
            [
                'formAnswer' => $fixture['answer']->id,
                'field' => 'cv',
                'filename' => 'private-cv.pdf',
            ],
        );

        $this->travel(2)->minutes();

        $this->get($signed)->assertForbidden();
    }

    public function test_non_file_field_cannot_be_used_to_read_a_stored_path(): void
    {
        Storage::fake('local');

        $owner = $this->member();
        $event = $this->event($owner);
        $form = $this->form($event);

        $path = 'form-uploads/'.$form->id.'/smuggled.pdf';
        Storage::disk('local')->put($path, '%PDF-1.7 smuggled');

        FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'notes',
            'label' => 'Notes',
            'order' => 1,
            'metadata' => [],
        ]);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'answers' => ['notes' => $path],
        ]);

        $url = route('dashboard.form-answers.files', [
            'formAnswer' => $answer->id,
            'field' => 'notes',
            'filename' => 'smuggled.pdf',
        ], false);

        $this->actingAs($owner)->get($url)->assertNotFound();
    }

    public function test_unknown_field_returns_not_found(): void
    {
        Storage::fake('local');

        $owner = $this->member();
        $event = $this->event($owner);
        $form = $this->form($event);
        $fixture = $this->answerWithFile($event, $form, $owner);

        $url = route('dashboard.form-answers.files', [
            'formAnswer' => $fixture['answer']->id,
            'field' => 'photo',
            'filename' => 'photo.jpg',
        ], false);

        $this->actingAs($owner)->get($url)->assertNotFound();
    }

    public function test_legacy_public_file_stays_reachable_only_through_authorized_route(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->member();
        $event = $this->event($owner);
        $form = $this->form($event);
        $fixture = $this->answerWithFile($event, $form, $owner, disk: 'public');

        $this->actingAs($owner)->get($fixture['url'])->assertOk();
        $this->assertContains($this->get('/storage/'.$fixture['path'])->status(), [403, 404]);
    }
}

<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormPurpose;
use App\Enums\FormStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use App\Services\Recruitment\OprecFormDefinition;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Draft form server-side: pembuatan idempoten, autosave tanpa publish,
 * publikasi, dan karantina draft dari semua jalur peserta/publik.
 */
class FormDraftTest extends TestCase
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

    private function organizer(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    /** Event milik $owner: pembuat event adalah satu-satunya admin yang boleh mengelola form-nya. */
    private function eventOwnedBy(User $owner, array $overrides = []): Event
    {
        return Event::factory()->create(array_merge([
            'created_by' => $owner->id,
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
            'quota' => 100,
            'registered_count' => 0,
        ], $overrides));
    }

    private function draft(Event $event, User $owner, array $overrides = []): Form
    {
        return Form::factory()->create(array_merge([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => FormStatus::Draft,
            'title' => '',
            'description' => '',
            'closed_at' => null,
            'metadata' => null,
            'visible_for' => [EventFormVisibility::Public->value],
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function completeFormPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Registration',
            'description' => 'Form description',
            'success_content' => null,
            'closed_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'visible_for' => [EventFormVisibility::Public->value],
            'banner_url' => null,
            'banner_caption' => null,
            'metadata' => [
                'purpose' => FormPurpose::Registration->value,
                'registration_mode' => 'single',
            ],
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function fieldRow(string $name = 'full_name', int $order = 1): array
    {
        return [
            'id' => (string) Str::uuid(),
            'label' => 'Name',
            'type' => 'input',
            'name' => $name,
            'order' => $order,
            'metadata' => ['type' => 'text', 'rules' => []],
            'is_append' => false,
        ];
    }

    private function draftPath(Event $event): string
    {
        return route('dashboard.events.forms.draft', ['event' => $event], false);
    }

    private function showPath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.show', ['event' => $event, 'form' => $form], false);
    }

    private function updatePath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.update', ['event' => $event, 'form' => $form], false);
    }

    private function fieldsPath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form], false);
    }

    private function destroyPath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.destroy', ['event' => $event, 'form' => $form], false);
    }

    /** URL halaman daftar form yang sudah dihapus (dulu `dashboard.events.forms.index`). */
    private function removedListPath(Event $event): string
    {
        return route('dashboard.events.show', ['event' => $event], false) . '/forms';
    }

    private function fillPath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false);
    }

    private function submitPath(Event $event, Form $form): string
    {
        return route('dashboard.forms.submission', ['event' => $event, 'form' => $form], false);
    }

    // -------------------------------------------------------------------------
    // 1. Pembuatan draft + idempotensi
    // -------------------------------------------------------------------------

    public function test_creator_creates_server_side_draft_placeholder(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);

        $response = $this->actingAs($owner)->postJson($this->draftPath($event));

        $response->assertOk()
            ->assertJsonStructure([
                'form' => [
                    'id', 'title', 'description', 'success_content', 'closed_at', 'visible_for',
                    'event_id', 'banner_url', 'banner_caption', 'metadata', 'purpose', 'status',
                    'is_draft', 'edit_url',
                ],
                'fields',
            ])
            ->assertJsonPath('form.status', FormStatus::Draft->value)
            ->assertJsonPath('form.is_draft', true)
            ->assertJsonPath('fields', []);

        $form = Form::query()->findOrFail($response->json('form.id'));

        $this->assertTrue($form->isDraft());
        $this->assertSame(FormStatus::Draft, $form->status);
        $this->assertSame('', $form->title);
        $this->assertSame('', $form->description);
        $this->assertNull($form->closed_at);
        $this->assertNull($form->metadata);
        $this->assertSame($owner->id, $form->created_by);
        $this->assertSame($event->id, $form->event_id);
        $this->assertSame(
            [EventFormVisibility::Public->value],
            $form->visible_for->map(fn (EventFormVisibility $v): string => $v->value)->values()->all(),
        );
        $this->assertSame(
            $this->showPath($event, $form),
            $response->json('form.edit_url'),
        );
    }

    public function test_draft_creation_is_idempotent_per_user_and_event(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);

        $first = $this->actingAs($owner)->postJson($this->draftPath($event))->assertOk();
        $second = $this->actingAs($owner)->postJson($this->draftPath($event))->assertOk();

        $this->assertSame($first->json('form.id'), $second->json('form.id'));
        $this->assertSame(1, Form::query()
            ->where('event_id', $event->id)
            ->where('created_by', $owner->id)
            ->where('status', FormStatus::Draft->value)
            ->count());
    }

    public function test_member_cannot_create_a_draft(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);

        $this->actingAs($this->member())
            ->postJson($this->draftPath($event))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, Form::query()->where('event_id', $event->id)->count());
    }

    public function test_non_creator_organizer_with_update_permission_cannot_create_a_draft(): void
    {
        $event = $this->eventOwnedBy($this->organizer());
        $otherOrganizer = $this->organizer();

        $this->actingAs($otherOrganizer)
            ->postJson($this->draftPath($event))
            ->assertForbidden();

        $this->assertSame(0, Form::query()->where('event_id', $event->id)->count());
    }

    public function test_non_creator_super_admin_cannot_create_a_draft(): void
    {
        $event = $this->eventOwnedBy($this->organizer());
        $superAdmin = $this->organizer('super-admin');

        $this->actingAs($superAdmin)
            ->postJson($this->draftPath($event))
            ->assertForbidden();

        $this->assertSame(0, Form::query()->where('event_id', $event->id)->count());
    }

    public function test_draft_snapshot_resumes_persisted_header_and_fields_after_reload(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);

        $created = $this->actingAs($owner)->postJson($this->draftPath($event))->assertOk();
        $form = Form::query()->findOrFail($created->json('form.id'));

        // Urutan autosave halaman create: fields selalu, settings saat header lengkap.
        $this->actingAs($owner)
            ->postJson($this->fieldsPath($event, $form), ['fields' => [$this->fieldRow()]])
            ->assertOk();
        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), $this->completeFormPayload([
                'title' => 'Resumed Header',
                'description' => 'Resumed description',
            ]))
            ->assertOk();

        $reloaded = $this->actingAs($owner)->postJson($this->draftPath($event))->assertOk();

        $this->assertSame($form->id, $reloaded->json('form.id'));
        $this->assertSame('Resumed Header', $reloaded->json('form.title'));
        $this->assertSame('Resumed description', $reloaded->json('form.description'));
        $this->assertNotNull($reloaded->json('form.closed_at'));
        $this->assertSame(FormStatus::Draft->value, $reloaded->json('form.status'));
        $this->assertCount(1, $reloaded->json('fields'));
        $this->assertSame('full_name', $reloaded->json('fields.0.name'));
        $this->assertSame(1, Form::query()->where('event_id', $event->id)->count());
    }

    public function test_draft_snapshot_returns_fields_ordered_by_order(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);

        foreach ([['second', 2], ['first', 1]] as [$name, $order]) {
            FormField::factory()->create([
                'form_id' => $form->id,
                'name' => $name,
                'label' => ucfirst($name),
                'input_type' => 'input',
                'order' => $order,
                'metadata' => ['type' => 'text', 'rules' => []],
            ]);
        }

        $response = $this->actingAs($owner)->postJson($this->draftPath($event))->assertOk();

        $this->assertSame(['first', 'second'], array_column($response->json('fields'), 'name'));
        $this->assertSame([1, 2], array_column($response->json('fields'), 'order'));
    }

    public function test_draft_snapshot_does_not_leak_submissions_or_sibling_form_data(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $draft = $this->draft($event, $owner);

        $sibling = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Sibling Form',
            'status' => FormStatus::Published,
        ]);
        FormAnswer::factory()->create([
            'form_id' => $sibling->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($owner)
            ->postJson($this->draftPath($event))
            ->assertOk()
            ->assertJsonPath('form.id', $draft->id)
            ->assertJsonMissingPath('submissions')
            ->assertJsonMissingPath('submissionsCount')
            ->assertJsonMissingPath('siblingForms')
            ->assertJsonMissingPath('forms')
            ->assertJsonMissingPath('form.submissions');

        $this->assertSame(['form', 'fields'], array_keys($response->json()));
        $this->assertStringNotContainsString('Sibling Form', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // 2. Autosave draft + publikasi
    // -------------------------------------------------------------------------

    public function test_draft_fields_channel_accepts_an_empty_array_and_clears_every_field(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);

        $form->update([
            'title' => 'Draft Title',
            'metadata' => ['purpose' => FormPurpose::Registration->value],
        ]);
        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'last_field',
            'label' => 'Last Field',
            'input_type' => 'input',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => []],
        ]);

        $this->actingAs($owner)
            ->postJson($this->fieldsPath($event, $form), ['fields' => []])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $form->refresh();
        $this->assertSame(0, $form->formFields()->count());
        $this->assertSame(1, FormField::onlyTrashed()->where('form_id', $form->id)->count());
        $this->assertSame('Draft Title', $form->title);
        $this->assertSame(['purpose' => FormPurpose::Registration->value], $form->metadata);
        $this->assertTrue($form->isDraft());
    }

    public function test_draft_fields_can_be_autosaved_without_publishing(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);

        $this->actingAs($owner)
            ->postJson($this->fieldsPath($event, $form), ['fields' => [$this->fieldRow()]])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $form->id,
            'name' => 'full_name',
        ]);
        $this->assertTrue($form->refresh()->isDraft());
    }

    public function test_draft_settings_can_be_saved_without_publishing(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);

        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), $this->completeFormPayload(['title' => 'Draft Header']))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $form->refresh();
        $this->assertSame('Draft Header', $form->title);
        $this->assertTrue($form->isDraft());
    }

    public function test_publish_flips_status_and_keeps_fields(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);

        $this->actingAs($owner)
            ->postJson($this->fieldsPath($event, $form), ['fields' => [$this->fieldRow()]])
            ->assertOk();

        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), $this->completeFormPayload(['publish' => true]))
            ->assertOk();

        $form->refresh();
        $this->assertSame(FormStatus::Published, $form->status);
        $this->assertFalse($form->isDraft());
        $this->assertSame(1, $form->formFields()->count());
    }

    public function test_publish_requires_full_validation_before_flipping_status(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);

        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), [
                'publish' => true,
                'title' => '',
                'description' => '',
                'visible_for' => [],
            ])
            ->assertStatus(422);

        $this->assertTrue($form->refresh()->isDraft());
    }

    public function test_publish_with_an_empty_title_still_reports_the_required_fields(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);

        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), [
                'publish' => true,
                'title' => '',
                'description' => '',
                'closed_at' => null,
                'visible_for' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'closed_at', 'visible_for']);

        $this->assertTrue($form->refresh()->isDraft());
    }

    public function test_draft_autosave_accepts_an_incomplete_header(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);

        // Header kosong tetap boleh disimpan (placeholder draft).
        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), ['autosave' => true, 'title' => ''])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('', $form->refresh()->title);

        // Hanya description yang dikirim: title/closed_at tidak boleh ikut ditimpa.
        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), [
                'autosave' => true,
                'description' => 'Partial description',
            ])
            ->assertOk()
            ->assertJsonMissingValidationErrors(['title', 'description', 'closed_at', 'visible_for']);

        $form->refresh();
        $this->assertSame('', $form->title);
        $this->assertSame('Partial description', $form->description);
        $this->assertNull($form->closed_at);
        $this->assertTrue($form->isDraft());

        // Payload hanya flag: tidak ada perubahan, tetap 200.
        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), ['autosave' => true])
            ->assertOk();

        $form->refresh();
        $this->assertSame('', $form->title);
        $this->assertSame('Partial description', $form->description);
        $this->assertNull($form->closed_at);
        $this->assertNull($form->metadata);
        $this->assertTrue($form->isDraft());
    }

    public function test_draft_autosave_keeps_the_field_sync_while_the_header_is_incomplete(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);

        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), [
                'autosave' => true,
                'title' => '',
                'fields' => [$this->fieldRow()],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $form->refresh();
        $this->assertSame('', $form->title);
        $this->assertTrue($form->isDraft());
        $this->assertSame(1, $form->formFields()->count());
    }

    public function test_draft_autosave_persists_the_partial_header_across_reload(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);

        $created = $this->actingAs($owner)->postJson($this->draftPath($event))->assertOk();
        $form = Form::query()->findOrFail($created->json('form.id'));

        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), ['autosave' => true, 'title' => 'Partial Title'])
            ->assertOk();

        $reloaded = $this->actingAs($owner)->postJson($this->draftPath($event))->assertOk();

        $this->assertSame($form->id, $reloaded->json('form.id'));
        $this->assertSame('Partial Title', $reloaded->json('form.title'));
        $this->assertSame('', $reloaded->json('form.description'));
        $this->assertSame(FormStatus::Draft->value, $reloaded->json('form.status'));
        $this->assertSame(1, Form::query()->where('event_id', $event->id)->count());
    }

    public function test_autosave_flag_is_ignored_on_a_published_form(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Published Title',
            'status' => FormStatus::Published,
        ]);

        // Parsial + autosave: aturan penuh tetap berlaku (flag diabaikan).
        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), ['autosave' => true, 'title' => 'Changed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description', 'closed_at', 'visible_for']);

        // Payload penuh + autosave: tersimpan seperti update biasa, status tetap published.
        $this->actingAs($owner)
            ->putJson($this->updatePath($event, $form), $this->completeFormPayload([
                'autosave' => true,
                'title' => 'Changed',
            ]))
            ->assertOk();

        $form->refresh();
        $this->assertSame('Changed', $form->title);
        $this->assertSame(FormStatus::Published, $form->status);
    }

    // -------------------------------------------------------------------------
    // 3. Draft tidak bocor ke jalur publik / peserta / laporan
    // -------------------------------------------------------------------------

    public function test_draft_is_hidden_from_the_public_event_page(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $published = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Alpha Published',
            'status' => FormStatus::Published,
            'visible_for' => [EventFormVisibility::Public->value],
        ]);
        $this->draft($event, $owner, ['title' => 'Hidden Draft']);

        $this->get(route('events.show', ['event' => $event->slug], false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('EventDetail')
                ->has('forms', 1)
                ->where('forms.0.id', $published->id)
                ->where('forms.0.title', 'Alpha Published'));
    }

    public function test_draft_is_denied_for_fill_and_submit(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner, ['title' => 'Hidden Draft']);
        $member = $this->member();

        $this->actingAs($member)
            ->get($this->fillPath($event, $form))
            ->assertNotFound();

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta'])
            ->assertRedirect($this->fillPath($event, $form));

        $this->assertDatabaseMissing('form_answers', [
            'form_id' => $form->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_draft_is_hidden_from_the_registration_form_picker(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $published = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Alpha Published',
            'status' => FormStatus::Published,
            'visible_for' => [EventFormVisibility::Public->value],
        ]);
        $this->draft($event, $owner, ['title' => 'Hidden Draft']);

        $this->actingAs($this->member())
            ->get(route('dashboard.user.events.register', ['event_segment' => $event->slug], false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/User/EventRegistrationPickForm')
                ->has('forms', 1)
                ->where('forms.0.id', $published->id));
    }

    public function test_draft_is_hidden_from_the_member_event_detail_form_list(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $published = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Alpha Published',
            'status' => FormStatus::Published,
            'metadata' => ['purpose' => FormPurpose::Other->value],
            'visible_for' => [EventFormVisibility::Public->value],
        ]);
        $this->draft($event, $owner, [
            'title' => 'Hidden Draft',
            'metadata' => ['purpose' => FormPurpose::Other->value],
        ]);

        $this->actingAs($this->member())
            ->get(route('dashboard.user.events.show', ['event_segment' => $event->slug], false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/User/EventDetail')
                ->has('participantForms', 1)
                ->where('participantForms.0.id', $published->id));
    }

    public function test_draft_is_hidden_from_registrants_and_the_registrations_export(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner, ['title' => 'Hidden Draft Form']);

        $participant = User::factory()->create();
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard.events.registrants', ['event' => $event], false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('forms', 0)->has('registrants', 0));

        $export = $this->actingAs($owner)
            ->get(route('dashboard.events.exports.registrations-csv', $event));

        $export->assertOk();
        $content = $export->streamedContent();

        $this->assertStringNotContainsString('Hidden Draft Form', $content);
        $this->assertStringNotContainsString($answer->id, $content);
    }

    public function test_draft_is_skipped_by_the_oprec_form_definition(): void
    {
        $published = Form::factory()->create([
            'event_id' => null,
            'title' => OprecFormDefinition::FORM_TITLE,
            'status' => FormStatus::Published,
            'metadata' => ['oprec' => true, 'purpose' => FormPurpose::Other->value],
        ]);
        Form::factory()->create([
            'event_id' => null,
            'title' => 'Draft Oprec',
            'status' => FormStatus::Draft,
            'metadata' => ['oprec' => true, 'purpose' => FormPurpose::Other->value],
        ]);

        $this->assertSame(
            $published->id,
            app(OprecFormDefinition::class)->requiredForm()->id,
        );
    }

    public function test_oprec_definition_fails_loudly_when_only_a_draft_exists(): void
    {
        Form::factory()->create([
            'event_id' => null,
            'title' => OprecFormDefinition::FORM_TITLE,
            'status' => FormStatus::Draft,
            'metadata' => ['oprec' => true, 'purpose' => FormPurpose::Other->value],
        ]);

        $this->expectException(HttpException::class);

        app(OprecFormDefinition::class)->requiredForm();
    }

    // -------------------------------------------------------------------------
    // 4. Visibilitas admin: hanya pembuat event
    // -------------------------------------------------------------------------

    public function test_event_creator_sees_the_draft_with_the_badge_flag(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $published = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Alpha Published',
            'status' => FormStatus::Published,
        ]);
        $draft = $this->draft($event, $owner, ['title' => 'Zeta Draft']);

        $this->actingAs($owner)
            ->get(route('dashboard.events.show', ['event' => $event], false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Events/Show')
                ->has('forms', 2)
                ->where('forms.0.id', $published->id)
                ->where('forms.0.title', 'Alpha Published')
                ->where('forms.0.status', FormStatus::Published->value)
                ->where('forms.0.is_draft', false)
                ->where('forms.1.id', $draft->id)
                ->where('forms.1.title', 'Zeta Draft')
                ->where('forms.1.status', FormStatus::Draft->value)
                ->where('forms.1.is_draft', true));
    }

    public function test_non_creator_super_admin_only_sees_published_forms(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $published = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Alpha Published',
            'status' => FormStatus::Published,
        ]);
        $draft = $this->draft($event, $owner, ['title' => 'Zeta Draft']);
        $superAdmin = $this->organizer('super-admin');

        $this->actingAs($superAdmin)
            ->get(route('dashboard.events.show', ['event' => $event], false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Events/Show')
                ->has('forms', 1)
                ->where('forms.0.id', $published->id)
                ->where('forms.0.is_draft', false))
            ->assertDontSee($draft->id);
    }

    public function test_creator_can_open_the_draft_edit_page(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);

        $this->actingAs($owner)
            ->get($this->showPath($event, $form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Events/Forms/Show')
                ->where('form.id', $form->id)
                ->where('form.is_draft', true));
    }

    public function test_non_creator_cannot_open_the_draft(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);
        $superAdmin = $this->organizer('super-admin');

        $this->actingAs($superAdmin)
            ->get($this->showPath($event, $form))
            ->assertNotFound();

        $this->actingAs($superAdmin)
            ->putJson($this->updatePath($event, $form), $this->completeFormPayload(['publish' => true]))
            ->assertNotFound();

        $this->actingAs($superAdmin)
            ->putJson($this->updatePath($event, $form), ['autosave' => true, 'title' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($superAdmin)
            ->postJson($this->fieldsPath($event, $form), ['fields' => [$this->fieldRow()]])
            ->assertForbidden();

        // Payload array kosong pun tetap ditolak: guard kepemilikan draft
        // berjalan sebelum validasi isi.
        $this->actingAs($superAdmin)
            ->postJson($this->fieldsPath($event, $form), ['fields' => []])
            ->assertForbidden();

        $this->assertTrue($form->refresh()->isDraft());
        $this->assertSame(0, $form->formFields()->count());
    }

    public function test_published_form_of_another_creator_stays_accessible_to_the_policy(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $published = Form::factory()->create([
            'event_id' => $event->id,
            'status' => FormStatus::Published,
        ]);

        $this->actingAs($this->organizer('super-admin'))
            ->get($this->showPath($event, $published))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // 5. Halaman daftar form dihapus; draft tetap tampil di halaman detail event
    // -------------------------------------------------------------------------

    public function test_removed_event_form_list_page_returns_not_found_for_the_creator(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);

        $this->assertFalse(Route::has('dashboard.events.forms.index'));

        $this->actingAs($owner)
            ->get($this->removedListPath($event))
            ->assertNotFound();
    }

    public function test_destroy_redirects_to_the_event_show_page(): void
    {
        $owner = $this->organizer();
        $event = $this->eventOwnedBy($owner);
        $form = $this->draft($event, $owner);

        $this->actingAs($owner)
            ->delete($this->destroyPath($event, $form))
            ->assertRedirect(route('dashboard.events.show', ['event' => $event], false));

        $this->assertSoftDeleted('forms', ['id' => $form->id]);
    }
}

<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormStatus;
use App\Http\Requests\FormShareLinkRequest;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\Form\FormShareBarcodeGenerator;
use App\Services\Form\FormShareLinkService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use PDOException;
use Tests\TestCase;

class FormShareLinkTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION_CONNECTION = 'form_share_link_migration_sqlite';

    private const MIGRATION_PATH = 'database/migrations/2026_11_12_000001_add_share_slug_to_forms_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_empty_request_generates_and_persists_a_valid_alias(): void
    {
        $form = Form::factory()->create();

        $alias = app(FormShareLinkService::class)->upsert($form, '');

        $this->assertMatchesRegularExpression('/\A[a-z0-9-]{3,50}\z/', $alias);
        $this->assertSame($alias, $form->refresh()->share_slug);
        $this->assertDatabaseHas('forms', ['id' => $form->id, 'share_slug' => $alias]);
    }

    public function test_generated_alias_retries_after_a_candidate_collision(): void
    {
        $occupied = Form::factory()->create(['share_slug' => 'collision12']);
        $target = Form::factory()->create();

        Str::createRandomStringsUsingSequence(['collision12', 'available12']);

        try {
            $alias = app(FormShareLinkService::class)->upsert($target, null);
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertSame('available12', $alias);
        $this->assertSame('collision12', $occupied->refresh()->share_slug);
        $this->assertSame('available12', $target->refresh()->share_slug);
    }

    public function test_custom_alias_is_persisted(): void
    {
        $form = Form::factory()->create();

        $alias = app(FormShareLinkService::class)->upsert($form, 'spring-registration');

        $this->assertSame('spring-registration', $alias);
        $this->assertSame('spring-registration', $form->refresh()->share_slug);
    }

    public function test_duplicate_alias_is_a_field_validation_error(): void
    {
        $first = Form::factory()->create();
        $second = Form::factory()->create();
        $service = app(FormShareLinkService::class);

        $service->upsert($first, 'shared-form');

        try {
            $service->upsert($second, 'shared-form');
            $this->fail('Expected a validation exception for a duplicate alias.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('alias', $exception->errors());
        }
    }

    public function test_postgres_unique_race_is_converted_to_a_field_validation_error(): void
    {
        $form = Form::factory()->create();
        $pdoException = new PDOException('duplicate key value violates unique constraint', 23505);
        $uniqueException = new UniqueConstraintViolationException(
            'pgsql',
            'update forms set share_slug = ? where id = ?',
            ['raced-form', $form->id],
            $pdoException,
        );
        $mock = Mockery::mock($form)->makePartial();
        $mock->shouldReceive('update')->once()->andThrow($uniqueException);

        $this->expectException(ValidationException::class);
        app(FormShareLinkService::class)->upsert($mock, 'raced-form');
    }

    public function test_reserved_aliases_are_rejected(): void
    {
        foreach (FormShareLinkRequest::RESERVED_ALIASES as $alias) {
            $request = FormShareLinkRequest::create('/', 'POST', ['alias' => $alias]);
            $validator = Validator::make($request->all(), $request->rules());

            $this->assertTrue($validator->fails(), "Alias [{$alias}] should be reserved.");
            $this->assertArrayHasKey('alias', $validator->errors()->toArray());
        }
    }

    public function test_invalid_alias_characters_and_lengths_are_rejected(): void
    {
        foreach (['ab', 'contains_underscore', 'Uppercase', str_repeat('a', 51)] as $alias) {
            $request = FormShareLinkRequest::create('/', 'POST', ['alias' => $alias]);
            $validator = Validator::make($request->all(), $request->rules());

            $this->assertTrue($validator->fails(), "Alias [{$alias}] should be invalid.");
            $this->assertArrayHasKey('alias', $validator->errors()->toArray());
        }
    }

    public function test_boundary_alias_lengths_are_accepted_and_persisted(): void
    {
        foreach ([str_repeat('a', 3), str_repeat('b', 50)] as $alias) {
            $request = FormShareLinkRequest::create('/', 'POST', ['alias' => $alias]);
            $validator = Validator::make($request->all(), $request->rules());

            $this->assertFalse($validator->fails(), "Alias [{$alias}] should be valid.");
            $validated = $validator->validated();
            $form = Form::factory()->create();
            $persistedAlias = app(FormShareLinkService::class)->upsert($form, $validated['alias']);

            $this->assertSame($alias, $persistedAlias);
            $this->assertSame($alias, $form->refresh()->share_slug);
        }
    }

    public function test_replacing_an_alias_releases_the_old_alias(): void
    {
        $first = Form::factory()->create();
        $second = Form::factory()->create();
        $service = app(FormShareLinkService::class);

        $service->upsert($first, 'old-alias');
        $service->upsert($first, 'new-alias');

        $this->assertSame('new-alias', $first->refresh()->share_slug);
        $service->upsert($second, 'old-alias');

        $this->assertSame('old-alias', $second->refresh()->share_slug);
    }

    public function test_share_alias_is_mass_assignable_and_selected_for_route_binding(): void
    {
        $form = Form::factory()->create(['share_slug' => 'persisted-alias']);

        $resolved = $form->resolveRouteBindingQuery(Form::query(), $form->id)->firstOrFail();

        $this->assertSame('persisted-alias', $resolved->share_slug);
    }

    public function test_share_slug_migration_can_be_applied_and_reverted_without_other_columns(): void
    {
        $this->useMigrationScratchDatabase();

        try {
            $this->assertSame(0, $this->artisanOnMigrationConnection('migrate'));
            $schema = Schema::connection(self::MIGRATION_CONNECTION);
            $this->assertTrue($schema->hasColumn('forms', 'share_slug'));
            $this->assertTrue($schema->hasIndex('forms', 'forms_share_slug_unique'));
            $this->assertTrue($schema->hasColumn('forms', 'title'));

            $this->assertSame(0, $this->artisanOnMigrationConnection('migrate:rollback', [
                '--path' => self::MIGRATION_PATH,
            ]));
            $this->assertFalse($schema->hasColumn('forms', 'share_slug'));
            $this->assertFalse($schema->hasIndex('forms', 'forms_share_slug_unique'));
            $this->assertTrue($schema->hasColumn('forms', 'title'));
        } finally {
            DB::purge(self::MIGRATION_CONNECTION);
        }
    }

    public function test_non_owner_organizer_cannot_update_a_form_share_link(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('admin');
        $otherOrganizer = User::factory()->create();
        $otherOrganizer->assignRole('admin');
        $event = $this->publishedEvent($owner);
        $form = $this->publishedForm($event);

        $this->actingAs($otherOrganizer)
            ->putJson($this->shareUpdateUrl($event, $form), ['alias' => 'not-allowed'])
            ->assertForbidden();

        $this->assertNull($form->refresh()->share_slug);
    }

    public function test_owner_can_update_share_link_and_receives_relative_payload(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('admin');
        $event = $this->publishedEvent($owner);
        $form = $this->publishedForm($event);

        $participantPath = route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false);
        $adminPath = route('dashboard.events.forms.show', [
            'event' => $event,
            'form' => $form,
            'tab' => 'editor',
        ], false);

        $this->actingAs($owner)
            ->putJson($this->shareUpdateUrl($event, $form), ['alias' => 'spring-form'])
            ->assertOk()
            ->assertJson([
                'participant_path' => $participantPath,
                'admin_path' => $adminPath,
                'short_path' => '/s/spring-form',
                'barcode_path' => route('dashboard.events.forms.share-link.barcode', [
                    'event' => $event,
                    'form' => $form,
                ], false),
                'update_url' => $this->shareUpdateUrl($event, $form),
                'alias' => 'spring-form',
                'can_share_participant' => true,
            ]);
    }

    public function test_public_alias_redirects_to_participant_fill_route(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');
        $event = $this->publishedEvent();
        $form = $this->publishedForm($event, ['share_slug' => 'public-form']);
        $participantPath = route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false);

        $this->actingAs($member)
            ->get('/s/public-form')
            ->assertRedirect($participantPath);
    }

    public function test_guest_alias_visit_preserves_participant_intended_path_through_login(): void
    {
        $user = User::factory()->create();
        $event = $this->publishedEvent();
        $form = $this->publishedForm($event, ['share_slug' => 'guest-form']);
        $participantPath = route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false);

        $this->followingRedirects()
            ->get('/s/guest-form')
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));

        $this->assertSame($participantPath, session('url.intended'));

        $this->post(route('auth.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect($participantPath);
    }

    public function test_authenticated_member_reaches_published_participant_form(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');
        $event = $this->publishedEvent();
        $form = $this->publishedForm($event, ['share_slug' => 'member-form']);
        $participantPath = route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false);

        $this->actingAs($member)
            ->get('/s/member-form')
            ->assertRedirect($participantPath);

        $this->actingAs($member)
            ->get($participantPath)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Events/Forms/Fill')
                ->where('accessStatus', 'allowed'));
    }

    public function test_released_alias_returns_not_found_after_replacement(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('admin');
        $event = $this->publishedEvent($owner);
        $form = $this->publishedForm($event, ['share_slug' => 'old-form']);

        $this->actingAs($owner)
            ->putJson($this->shareUpdateUrl($event, $form), ['alias' => 'new-form'])
            ->assertOk();

        $this->get('/s/old-form')->assertNotFound();
        $this->get('/s/new-form')->assertRedirect(
            route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false)
        );
    }

    public function test_draft_alias_returns_not_found(): void
    {
        $event = $this->publishedEvent();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'status' => FormStatus::Draft,
            'share_slug' => 'draft-form',
        ]);

        $this->get('/s/draft-form')->assertNotFound();
    }

    public function test_visibility_blocked_published_alias_keeps_guard_and_exposes_no_fields(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');
        $event = $this->publishedEvent();
        $form = $this->publishedForm($event, [
            'share_slug' => 'admin-only-form',
            'visible_for' => [EventFormVisibility::Admin->value],
        ]);
        FormField::factory()->create([
            'form_id' => $form->id,
            'label' => 'Private participant field',
            'name' => 'private_participant_field',
        ]);
        $participantPath = route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false);

        $this->actingAs($member)
            ->get('/s/admin-only-form')
            ->assertRedirect($participantPath);

        $this->actingAs($member)
            ->get($participantPath)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('accessStatus', 'not_visible')
                ->where('fields', []));
    }

    public function test_unknown_and_deleted_aliases_return_not_found_without_form_fields(): void
    {
        $event = $this->publishedEvent();
        $form = $this->publishedForm($event, ['share_slug' => 'deleted-form']);
        FormField::factory()->create([
            'form_id' => $form->id,
            'label' => 'Deleted form field',
            'name' => 'deleted_form_field',
        ]);

        $this->get('/s/unknown-form')
            ->assertNotFound()
            ->assertDontSee('Deleted form field');

        $form->delete();

        $this->get('/s/deleted-form')
            ->assertNotFound()
            ->assertDontSee('Deleted form field');
    }

    public function test_mismatched_event_and_form_binding_returns_not_found_without_form_fields(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');
        $formEvent = $this->publishedEvent();
        $requestEvent = $this->publishedEvent();
        $form = $this->publishedForm($formEvent);
        FormField::factory()->create([
            'form_id' => $form->id,
            'label' => 'Mismatched binding field',
            'name' => 'mismatched_binding_field',
        ]);

        $response = $this->actingAs($member)->get(route('dashboard.events.forms.fill', [
            'event' => $requestEvent,
            'form' => $form,
        ]));

        $response
            ->assertNotFound()
            ->assertDontSee('Mismatched binding field');
    }

    public function test_closed_form_alias_keeps_existing_access_guard_authoritative(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');
        $event = $this->publishedEvent();
        $form = $this->publishedForm($event, [
            'share_slug' => 'closed-form',
            'closed_at' => now()->subMinute(),
        ]);
        $participantPath = route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false);

        $this->actingAs($member)
            ->get('/s/closed-form')
            ->assertRedirect($participantPath);

        $this->actingAs($member)
            ->get($participantPath)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('accessStatus', 'form_closed'));
    }

    public function test_barcode_is_png_and_requires_event_owner_authorization(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('admin');
        $otherOrganizer = User::factory()->create();
        $otherOrganizer->assignRole('admin');
        $event = $this->publishedEvent($owner);
        $form = $this->publishedForm($event);
        $barcodeUrl = route('dashboard.events.forms.share-link.barcode', [
            'event' => $event,
            'form' => $form,
        ]);

        $response = $this->actingAs($owner)
            ->get($barcodeUrl.'?url=https://evil.example')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $png = $response->getContent();
        $participantUrl = route('dashboard.events.forms.fill', [
            'event' => $event,
            'form' => $form,
        ]);
        $relativeParticipantPath = route('dashboard.events.forms.fill', [
            'event' => $event,
            'form' => $form,
        ], false);
        $expectedPng = app(FormShareBarcodeGenerator::class)->pngForUrl($participantUrl);
        $relativePng = app(FormShareBarcodeGenerator::class)->pngForUrl($relativeParticipantPath);
        $dimensions = getimagesizefromstring($png);

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $this->assertSame($expectedPng, $png, 'The barcode must encode the server-built absolute participant URL.');
        $this->assertNotSame($relativePng, $png, 'The barcode must not encode a relative participant path.');
        $this->assertIsArray($dimensions);
        $this->assertGreaterThan(0, $dimensions[0]);
        $this->assertGreaterThan(0, $dimensions[1]);
        $this->assertLessThanOrEqual(256, $dimensions[0]);
        $this->assertLessThanOrEqual(256, $dimensions[1]);

        $this->actingAs($otherOrganizer)
            ->get($barcodeUrl)
            ->assertForbidden();
    }

    public function test_form_show_includes_relative_share_payload(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('admin');
        $event = $this->publishedEvent($owner);
        $form = $this->publishedForm($event, ['share_slug' => 'editor-form']);

        $this->actingAs($owner)
            ->get(route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Events/Forms/Show')
                ->where('formShare', [
                    'participant_path' => route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false),
                    'admin_path' => route('dashboard.events.forms.show', [
                        'event' => $event,
                        'form' => $form,
                        'tab' => 'editor',
                    ], false),
                    'short_path' => '/s/editor-form',
                    'barcode_path' => route('dashboard.events.forms.share-link.barcode', [
                        'event' => $event,
                        'form' => $form,
                    ], false),
                    'update_url' => $this->shareUpdateUrl($event, $form),
                    'alias' => 'editor-form',
                    'can_share_participant' => true,
                ]));
    }

    private function publishedEvent(?User $owner = null): Event
    {
        return Event::factory()->create([
            'created_by' => $owner?->id,
            'status' => EventStatus::Published,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDay(),
            'quota' => 100,
            'registered_count' => 0,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function publishedForm(Event $event, array $overrides = []): Form
    {
        return Form::factory()->create(array_merge([
            'event_id' => $event->id,
            'status' => FormStatus::Published,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDay(),
        ], $overrides));
    }

    private function shareUpdateUrl(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.share-link', ['event' => $event, 'form' => $form], false);
    }

    private function useMigrationScratchDatabase(): void
    {
        config([
            'database.connections.'.self::MIGRATION_CONNECTION => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge(self::MIGRATION_CONNECTION);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function artisanOnMigrationConnection(string $command, array $parameters = []): int
    {
        return Artisan::call($command, array_merge($parameters, [
            '--database' => self::MIGRATION_CONNECTION,
            '--force' => true,
        ]));
    }
}

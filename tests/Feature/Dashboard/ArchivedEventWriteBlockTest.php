<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Enums\FormStatus;
use App\Http\Middleware\EnsureEventIsNotArchived;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Acara terarsip bersifat read-only: setiap route event-scoped selain allowlist
 * (detail, registration-status, restore, force-delete, archived index) wajib
 * ditolak 409 untuk request Inertia/HTML maupun JSON.
 */
class ArchivedEventWriteBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * @return array{0: Event, 1: Form}
     */
    private function archivedEventWithForm(User $owner): array
    {
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $event->delete();

        // Form published ikut terarsip bersama event (EventObserver), jadi hanya
        // form draft yang masih bisa di-resolve route binding: jalur tulis tersisa.
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => FormStatus::Draft,
        ]);

        return [$event, $form];
    }

    private function archivedEvent(User $owner): Event
    {
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $event->delete();

        return $event;
    }

    public function test_update_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->put(route('dashboard.events.update', $event), ['title' => 'Ubah'])
            ->assertStatus(409);

        $this->actingAs($admin)
            ->patch(route('dashboard.events.update', $event), ['title' => 'Ubah'])
            ->assertStatus(409);

        $this->assertDatabaseMissing('events', ['id' => $event->id, 'title' => 'Ubah']);
    }

    public function test_status_update_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        $event = $this->archivedEvent($admin);

        $this->actingAs($admin)
            ->patch(route('dashboard.events.status', $event), ['status' => 'published'])
            ->assertStatus(409);
    }

    public function test_soft_delete_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        $event = $this->archivedEvent($admin);

        $this->actingAs($admin)
            ->delete(route('dashboard.events.destroy', $event))
            ->assertStatus(409);
    }

    public function test_form_create_page_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->get(route('dashboard.events.forms.create', $event))
            ->assertStatus(409);
    }

    public function test_form_store_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->post(route('dashboard.events.forms.store', $event), ['title' => 'Form Baru'])
            ->assertStatus(409);

        $this->assertDatabaseMissing('forms', ['event_id' => $event->id, 'title' => 'Form Baru']);
    }

    public function test_form_draft_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->post(route('dashboard.events.forms.draft', $event), ['autosave' => true])
            ->assertStatus(409);
    }

    public function test_form_edit_page_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event, $form] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->get(route('dashboard.events.forms.edit', ['event' => $event, 'form' => $form]))
            ->assertStatus(409);
    }

    public function test_form_update_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event, $form] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->put(route('dashboard.events.forms.update', ['event' => $event, 'form' => $form]), ['title' => 'Ubah'])
            ->assertStatus(409);
    }

    public function test_form_destroy_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event, $form] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->delete(route('dashboard.events.forms.destroy', ['event' => $event, 'form' => $form]))
            ->assertStatus(409);

        $this->assertDatabaseHas('forms', ['id' => $form->id]);
    }

    public function test_share_link_update_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event, $form] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->put(route('dashboard.events.forms.share-link', ['event' => $event, 'form' => $form]), ['share_slug' => 'slug-baru'])
            ->assertStatus(409);
    }

    public function test_field_operations_are_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event, $form] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->post(route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form]), [
                'context' => 'editor',
                'operations' => [],
            ])
            ->assertStatus(409);
    }

    public function test_image_upload_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event, $form] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->post(route('dashboard.events.forms.images', ['event' => $event, 'form' => $form]))
            ->assertStatus(409);
    }

    public function test_bulk_review_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event, $form] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->patch(route('dashboard.events.forms.submissions.bulk-review', ['event' => $event, 'form' => $form]), [
                'answer_ids' => [],
                'action' => 'approve',
            ])
            ->assertStatus(409);
    }

    public function test_single_answer_review_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event, $form] = $this->archivedEventWithForm($admin);
        $answer = FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)
            ->patch(route('dashboard.events.forms.submissions.review', [
                'event' => $event,
                'form' => $form,
                'formAnswer' => $answer,
            ]), ['action' => 'approve'])
            ->assertStatus(409);
    }

    public function test_csv_export_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event, $form] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->get(route('dashboard.events.forms.answers-csv', ['event' => $event, 'form' => $form]))
            ->assertStatus(409);
    }

    public function test_event_edit_page_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        $event = $this->archivedEvent($admin);

        $this->actingAs($admin)
            ->get(route('dashboard.events.edit', $event))
            ->assertStatus(409);
    }

    public function test_answers_zip_export_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        [$event, $form] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->get(route('dashboard.events.forms.answers-zip', ['event' => $event, 'form' => $form]))
            ->assertStatus(409);
    }

    public function test_registrations_csv_export_is_blocked_for_archived_event(): void
    {
        $admin = $this->admin();
        $event = $this->archivedEvent($admin);

        $this->actingAs($admin)
            ->get(route('dashboard.events.exports.registrations-csv', $event))
            ->assertStatus(409);
    }

    public function test_inertia_request_renders_the_app_error_page_for_409(): void
    {
        $admin = $this->admin();
        $event = $this->archivedEvent($admin);
        $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

        // Navigasi Inertia (X-Inertia) menerima page JSON Error, bukan halaman mentah Laravel.
        $this->actingAs($admin)
            ->get(route('dashboard.events.edit', $event), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) $version,
            ])
            ->assertStatus(409)
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 409);

        // Muatan penuh (HTML) juga merender komponen Error yang sama.
        $this->actingAs($admin)
            ->get(route('dashboard.events.edit', $event))
            ->assertStatus(409)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 409));
    }

    public function test_json_request_gets_409_with_message(): void
    {
        $admin = $this->admin();
        [$event, $form] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->putJson(route('dashboard.events.forms.share-link', ['event' => $event, 'form' => $form]), [])
            ->assertStatus(409)
            ->assertJson(['message' => __('messages.event.archived.read_only')]);
    }

    public function test_allowlisted_routes_still_work_on_archived_event(): void
    {
        $admin = $this->admin();
        [$event] = $this->archivedEventWithForm($admin);

        $this->actingAs($admin)
            ->get(route('dashboard.events.show', $event))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('dashboard.events.registration-status', $event))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('dashboard.events.archived'))
            ->assertOk();

        $toRestore = $this->archivedEvent($admin);

        $this->actingAs($admin)
            ->post(route('dashboard.events.restore', $toRestore), ['return_to' => 'archived'])
            ->assertRedirect(route('dashboard.events.archived'));

        $this->assertNull($toRestore->fresh()?->deleted_at);

        $toForceDelete = $this->archivedEvent($admin);

        $this->actingAs($admin)
            ->delete(route('dashboard.events.force-delete', $toForceDelete))
            ->assertRedirect(route('dashboard.events.archived'));

        $this->assertDatabaseMissing('events', ['id' => $toForceDelete->id]);
    }

    /**
     * Registry guard: setiap route `{event}` pada namespace dashboard wajib
     * memakai middleware `event.not-archived` atau terdaftar di allowlist.
     *
     * Route peserta `dashboard.forms.submission` sengaja dikecualikan: guard-nya
     * menjawab 404 (bukan 409) agar status arsip tidak bocor ke peserta, dan
     * kontrak itu diuji EventAvailabilityGuardTest.
     */
    public function test_every_dashboard_event_route_is_guarded_or_allowlisted(): void
    {
        $intentionallyUnguarded = ['dashboard.forms.submission'];
        $router = app('router');

        $unguarded = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RegisteredRoute $route): bool => str_contains($route->uri(), '{event}'))
            ->filter(function (RegisteredRoute $route): bool {
                $name = $route->getName();

                return $name === null || str_starts_with($name, 'dashboard.');
            })
            ->reject(fn (RegisteredRoute $route): bool => in_array($route->getName(), EnsureEventIsNotArchived::READ_ONLY_ALLOWED_ROUTES, true))
            ->reject(fn (RegisteredRoute $route): bool => in_array($route->getName(), $intentionallyUnguarded, true))
            ->reject(fn (RegisteredRoute $route): bool => in_array(
                EnsureEventIsNotArchived::class,
                $router->gatherRouteMiddleware($route),
                true,
            ))
            ->map(fn (RegisteredRoute $route): string => ($route->getName() ?? '(unnamed)').' ['.$route->uri().']')
            ->values()
            ->all();

        $this->assertSame(
            [],
            $unguarded,
            'Route event-scoped tanpa middleware event.not-archived: '.implode(', ', $unguarded),
        );
    }

    /**
     * Route baru boleh menamai parameter event dengan nama lain; model tetap
     * ter-bind karena gerbang `withTrashed()` di Event memakai nama route, bukan
     * nama parameter, sehingga middleware harus memindai semua parameter.
     */
    public function test_route_with_differently_named_event_parameter_is_still_guarded(): void
    {
        Route::middleware(['web', 'event.not-archived'])
            ->get('/admin/events/{eventId}/tmp-write', static fn (Event $eventId): string => 'allowed')
            ->name('dashboard.events.tmp-write');

        $archived = $this->archivedEvent($this->admin());
        $active = Event::factory()->create(['created_by' => $archived->created_by]);

        $this->get('/admin/events/'.$archived->id.'/tmp-write')->assertStatus(409);
        $this->get('/admin/events/'.$active->id.'/tmp-write')->assertOk()->assertSee('allowed');
    }

    /** Route tanpa nama tidak bisa masuk allowlist, jadi default-deny harus menolaknya. */
    public function test_unnamed_event_route_cannot_bypass_the_guard(): void
    {
        $admin = $this->admin();
        $event = $this->archivedEvent($admin);

        $request = Request::create('/admin/events/'.$event->id.'/custom-action', 'POST');

        $route = new RegisteredRoute(['POST'], 'admin/events/{event}/custom-action', static fn () => response('ok'));
        $route->bind($request);
        $route->setParameter('event', $event);
        $request->setRouteResolver(static fn () => $route);

        try {
            app(EnsureEventIsNotArchived::class)->handle($request, static fn () => response('ok'));
            $this->fail('Route tanpa nama lolos dari guard event terarsip.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    /** @return array<string, mixed> */
    private function updatePayload(string $title): array
    {
        return [
            'title' => $title,
            'location' => 'Campus Hall',
            'description' => 'Deskripsi acara.',
            'registration_start' => now()->startOfDay()->toDateTimeString(),
            'registration_end' => now()->addDays(3)->endOfDay()->toDateTimeString(),
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'quota' => 50,
            'price' => '0',
            'session' => 'general',
            'category' => 'rkt',
        ];
    }

    public function test_active_event_update_is_not_blocked(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->put(route('dashboard.events.update', $event), $this->updatePayload('Judul Baru'))
            ->assertRedirect(route('dashboard.events.show', $event));

        $this->assertSame('Judul Baru', $event->fresh()?->title);
    }
}

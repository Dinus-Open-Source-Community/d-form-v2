<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class FormSubmissionThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Bucket limiter memakai cache array; kosongkan agar tiap test mulai dari nol.
        Cache::flush();
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
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

    /** Form publik yang masih terbuka dengan satu field wajib. */
    private function openForm(Event $event): Form
    {
        $form = Form::factory()->create([
            'event_id'    => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at'   => now()->addDays(30),
        ]);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => 'full_name',
            'label'      => 'Full Name',
            'order'      => 1,
            'metadata'   => ['type' => 'text', 'rules' => ['required' => true]],
        ]);

        return $form;
    }

    private function submitPath(Event $event, Form $form): string
    {
        return route('dashboard.forms.submission', ['event' => $event, 'form' => $form], false);
    }

    /** Habiskan kuota submit dengan payload yang ditolak validasi (tidak menulis baris). */
    private function exhaustSubmitQuota(User $user, string $path): void
    {
        $limit = (int) config('events.forms.submit_rate_limit_per_minute');
        $this->actingAs($user);

        for ($attempt = 1; $attempt <= $limit; $attempt++) {
            $this->post($path, [])->assertRedirect();
        }
    }

    /**
     * Kunci bucket `form-submit` untuk kombinasi user + route parameter tertentu,
     * dibangun tanpa request HTTP agar bisa diuji tanpa lookup DB.
     */
    private function submitLimiterKey(User $user, string $eventPath, string $formPath): string
    {
        $request = Request::create("/joined/{$eventPath}/forms/{$formPath}/submit", 'POST');
        $request->setUserResolver(static fn (): User => $user);

        $route = new Route(['POST'], '/joined/{event}/forms/{form}/submit', []);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        $limiter = RateLimiter::limiter('form-submit');
        $this->assertNotNull($limiter);

        $limit = $limiter($request);
        $this->assertInstanceOf(Limit::class, $limit);

        return $limit->key;
    }

    public function test_submit_beyond_limit_returns_429_without_saving_answer(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);
        $path   = $this->submitPath($event, $form);

        $this->exhaustSubmitQuota($member, $path);

        $this->actingAs($member)
            ->post($path, ['full_name' => 'Jane Doe'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertSame(0, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_submit_below_limit_still_saves_answer(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['full_name' => 'Jane Doe'])
            ->assertRedirect(route('dashboard.events.forms.submitted', [
                'event' => $event,
                'form'  => $form,
            ]));

        $this->assertDatabaseHas('form_answers', [
            'form_id' => $form->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_submit_quota_is_not_shared_between_participants(): void
    {
        $blocked = $this->member();
        $other   = $this->member();
        $event   = $this->openEvent();
        $form    = $this->openForm($event);
        $path    = $this->submitPath($event, $form);

        $this->exhaustSubmitQuota($blocked, $path);

        $this->actingAs($blocked)
            ->post($path, ['full_name' => 'Blocked'])
            ->assertStatus(429);

        // Kuota peserta lain pada form yang sama tidak ikut terpakai.
        $this->actingAs($other)
            ->post($path, ['full_name' => 'Other'])
            ->assertRedirect(route('dashboard.events.forms.submitted', [
                'event' => $event,
                'form'  => $form,
            ]));

        $this->assertSame(1, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_submit_quota_is_scoped_per_form_for_the_same_user(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $formA  = $this->openForm($event);
        $formB  = $this->openForm($event);

        $this->exhaustSubmitQuota($member, $this->submitPath($event, $formA));

        $this->actingAs($member)
            ->post($this->submitPath($event, $formA), ['full_name' => 'Form A'])
            ->assertStatus(429);

        // Form lain punya bucket sendiri untuk user yang sama.
        $this->actingAs($member)
            ->post($this->submitPath($event, $formB), ['full_name' => 'Form B'])
            ->assertRedirect(route('dashboard.events.forms.submitted', [
                'event' => $event,
                'form'  => $formB,
            ]));

        $this->assertSame(0, FormAnswer::query()->where('form_id', $formA->id)->count());
        $this->assertSame(1, FormAnswer::query()->where('form_id', $formB->id)->count());
    }

    public function test_submit_limiter_key_ignores_uuid_case_and_surrounding_space(): void
    {
        $member  = $this->member();
        $eventId = 'b1b0c1d2-e3f4-4a5b-8c9d-0e1f2a3b4c5d';
        $formId  = 'a1a2a3a4-b1b2-4c1c-8d2d-3e4f5a6b7c8d';

        $canonical = $this->submitLimiterKey($member, $eventId, $formId);

        // MySQL membandingkan UUID tanpa peduli huruf besar dan spasi ekor,
        // jadi ejaan itu tidak boleh membuka bucket baru.
        $this->assertSame($canonical, $this->submitLimiterKey($member, strtoupper($eventId), $formId));
        $this->assertSame($canonical, $this->submitLimiterKey($member, $eventId, $formId.'%20'));
    }

    public function test_check_email_beyond_limit_returns_429(): void
    {
        $member = $this->member();
        $limit  = (int) config('auth_throttle.email_check.max_attempts');

        for ($attempt = 1; $attempt <= $limit; $attempt++) {
            $this->actingAs($member)
                ->getJson(route('dashboard.users.check-email', ['email' => "probe{$attempt}@example.test"]))
                ->assertOk();
        }

        $this->actingAs($member)
            ->getJson(route('dashboard.users.check-email', ['email' => 'overflow@example.test']))
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_check_email_below_limit_answers_json(): void
    {
        $member = $this->member();

        $this->actingAs($member)
            ->getJson(route('dashboard.users.check-email', ['email' => 'nobody@example.test']))
            ->assertOk()
            ->assertJson(['exists' => false]);
    }
}

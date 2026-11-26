<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Route peserta (`/joined/{event}/forms/{form}/...`) menolak event terarsip
 * dengan 409, sementara event aktif tetap berjalan seperti sebelumnya.
 */
class JoinedEventFormFillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function member(): User
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        return $member;
    }

    /** Role admin memegang `events.list`, sama seperti organizer lain. */
    private function organizer(): User
    {
        $organizer = User::factory()->create();
        $organizer->assignRole('admin');

        return $organizer;
    }

    /** @return array{0: Event, 1: Form} */
    private function publishedEventWithForm(): array
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDay(),
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $form = Form::factory()->create(['event_id' => $event->id]);

        return [$event, $form];
    }

    /**
     * Event diarsipkan lebih dulu, lalu form published dibuat setelahnya:
     * state yang mungkin tersisa dari data lama atau jalur tulis yang bocor.
     * Form ini tidak ikut cascade arsip karena dibuat setelah event terhapus.
     *
     * @return array{0: Event, 1: Form}
     */
    private function archivedEventWithPublishedForm(): array
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDay(),
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $event->delete();

        $form = Form::factory()->create(['event_id' => $event->id]);

        return [$event, $form];
    }

    public function test_fill_route_returns_conflict_for_archived_event(): void
    {
        [$event, $form] = $this->archivedEventWithPublishedForm();

        $this->actingAs($this->member())
            ->get(route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form]))
            ->assertStatus(409);
    }

    public function test_submit_success_route_returns_conflict_for_archived_event(): void
    {
        [$event, $form] = $this->archivedEventWithPublishedForm();
        $member = $this->member();

        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $member->id]);

        $this->actingAs($member)
            ->get(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form]))
            ->assertStatus(409);
    }

    public function test_organizer_submission_for_archived_event_form_returns_not_found(): void
    {
        [$event, $form] = $this->publishedEventWithForm();

        // Arsip event ikut mengarsipkan form published (EventObserver), sehingga
        // route binding `{form}` menolak lebih dulu: kontrak 404 yang dipertahankan
        // oleh pengecualian `dashboard.forms.submission`, termasuk untuk organizer.
        $event->delete();

        $this->actingAs($this->organizer())
            ->post(route('dashboard.forms.submission', ['event' => $event, 'form' => $form]), [])
            ->assertNotFound();

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_fill_route_still_works_for_active_event(): void
    {
        [$event, $form] = $this->publishedEventWithForm();

        $this->actingAs($this->member())
            ->get(route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form]))
            ->assertOk();
    }

    public function test_submit_success_route_still_works_for_active_event(): void
    {
        [$event, $form] = $this->publishedEventWithForm();
        $member = $this->member();

        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $member->id]);

        $this->actingAs($member)
            ->get(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form]))
            ->assertOk();
    }
}

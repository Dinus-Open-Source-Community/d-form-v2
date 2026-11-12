<?php

namespace Tests\Feature\Forms;

use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Event draft/terhapus tidak boleh menerima submisi; review wajib milik event sendiri.
 */
class EventAvailabilityGuardTest extends TestCase
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /** @return array{0: Event, 1: Form} */
    private function eventWithForm(EventStatus $status, bool $trashed, ?User $owner = null): array
    {
        $event = Event::factory()->create([
            'status' => $status,
            'created_by' => $owner?->id,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDay(),
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        if ($trashed) {
            $event->delete();
        }

        $form = Form::factory()->create(['event_id' => $event->id]);

        return [$event, $form];
    }

    public function test_member_tidak_bisa_mengisi_form_event_draft(): void
    {
        [$event, $form] = $this->eventWithForm(EventStatus::Draft, false);

        $this->actingAs($this->member())
            ->get(route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form]))
            ->assertNotFound();
    }

    public function test_member_tidak_bisa_submit_ke_event_terhapus(): void
    {
        [$event, $form] = $this->eventWithForm(EventStatus::Published, true);

        $this->actingAs($this->member())
            ->post(route('dashboard.forms.submission', ['event' => $event, 'form' => $form]), [])
            ->assertNotFound();

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_admin_masih_bisa_membuka_form_event_draft(): void
    {
        [$event, $form] = $this->eventWithForm(EventStatus::Draft, false);

        $this->actingAs($this->admin())
            ->get(route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form]))
            ->assertOk();
    }

    public function test_admin_lain_tidak_bisa_review_submisi_event_orang_lain(): void
    {
        $owner = $this->admin();
        [$event, $form] = $this->eventWithForm(EventStatus::Published, false, $owner);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('dashboard.events.forms.submissions.review', [
                'event' => $event,
                'form' => $form,
                'formAnswer' => $answer,
            ]), ['review_status' => 'accepted'])
            ->assertForbidden();

        $this->assertSame(FormAnswerReviewStatus::Pending, $answer->fresh()?->review_status);
    }
}

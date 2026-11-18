<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pagination tab Jawaban hanya memuat satu halaman, sementara select-all memakai ID semua halaman. */
class FormAnswersPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function eventAndForm(User $owner): array
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'created_by' => $owner->id,
        ]);

        return [$event, Form::factory()->create(['event_id' => $event->id])];
    }

    public function test_answers_tab_loads_one_page_and_reports_pagination_metadata(): void
    {
        $owner = $this->admin();
        [$event, $form] = $this->eventAndForm($owner);

        FormAnswer::factory()->count(25)->create([
            'form_id' => $form->id,
            'user_id' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard.events.forms.show', [
                'event' => $event,
                'form' => $form,
                'tab' => 'jawaban',
                'per_page' => 10,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Events/Forms/Show')
                ->has('submissions', 10)
                ->where('submissionsCount', 25)
                ->where('submissionsPagination.current_page', 2)
                ->where('submissionsPagination.last_page', 3)
                ->where('submissionsPagination.per_page', 10)
                ->where('submissionsPagination.total', 25)
                ->has('selectableSubmissionIds', 25)
                ->where('bulkSelectableTotal', 25)
                ->where('bulkSelectableLimit', 100));
    }

    public function test_page_beyond_last_is_clamped_and_invalid_per_page_falls_back_to_default(): void
    {
        $owner = $this->admin();
        [$event, $form] = $this->eventAndForm($owner);

        FormAnswer::factory()->count(25)->create([
            'form_id' => $form->id,
            'user_id' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard.events.forms.show', [
                'event' => $event,
                'form' => $form,
                'tab' => 'jawaban',
                'per_page' => 999,
                'page' => 999,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('submissionsPagination.per_page', 20)
                ->where('submissionsPagination.last_page', 2)
                ->where('submissionsPagination.current_page', 2)
                ->has('submissions', 5));
    }

    public function test_select_all_only_includes_bulk_review_eligible_rows(): void
    {
        $owner = $this->admin();
        [$event, $form] = $this->eventAndForm($owner);

        FormAnswer::factory()->count(3)->create([
            'form_id' => $form->id,
            'user_id' => null,
        ]);
        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard.events.forms.show', [
                'event' => $event,
                'form' => $form,
                'tab' => 'jawaban',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('submissionsCount', 4)
                ->has('selectableSubmissionIds', 3)
                ->where('bulkSelectableTotal', 3));
    }
}

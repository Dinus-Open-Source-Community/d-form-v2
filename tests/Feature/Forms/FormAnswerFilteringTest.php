<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\FormAnswerReviewStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Filter daftar jawaban di tab Jawaban: pencarian nama/email/kode referral,
 * filter status review, dan filter keberadaan referral.
 */
class FormAnswerFilteringTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Event $event;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('admin');
        $this->event = Event::factory()->create(['created_by' => $this->owner->id]);
        $this->form = Form::factory()->create([
            'event_id' => $this->event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'referral_enabled' => true,
        ]);
    }

    public function test_search_matches_sender_name(): void
    {
        $match = $this->answer(['user_id' => $this->sender('Nadia Putri', 'nadia@example.test')]);
        $this->answer(['user_id' => $this->sender('Budi Santoso', 'budi@example.test')]);

        $this->actingAs($this->owner)
            ->get($this->showPath(['search' => 'nadia']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.id', $match->id)
                ->where('submissionsPagination.total', 1)
                // Badge tab tetap menghitung seluruh jawaban.
                ->where('submissionsCount', 2));
    }

    public function test_search_matches_referral_code(): void
    {
        $withCode = $this->answer();
        $this->redeem($this->code('UDIN-AB12'), $withCode);
        $this->answer();

        $this->actingAs($this->owner)
            ->get($this->showPath(['search' => 'AB12']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.id', $withCode->id));
    }

    public function test_review_status_filter_returns_only_matching_rows(): void
    {
        $accepted = $this->answer(['review_status' => FormAnswerReviewStatus::Accepted->value]);
        $this->answer(['review_status' => FormAnswerReviewStatus::Pending->value]);

        $this->actingAs($this->owner)
            ->get($this->showPath(['review_status' => 'accepted']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.id', $accepted->id)
                ->where('query.review_status', 'accepted'));
    }

    public function test_referral_filter_separates_rows_with_and_without_code(): void
    {
        $withCode = $this->answer();
        $this->redeem($this->code('UDIN-AB12'), $withCode);
        $without = $this->answer();

        $this->actingAs($this->owner)
            ->get($this->showPath(['referral' => 'with']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.id', $withCode->id));

        $this->actingAs($this->owner)
            ->get($this->showPath(['referral' => 'without']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.id', $without->id));
    }

    public function test_invalid_filter_values_are_ignored(): void
    {
        $this->answer();
        $this->answer();

        $this->actingAs($this->owner)
            ->get($this->showPath(['review_status' => 'unknown', 'referral' => 'UDIN-AB12']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 2)
                ->where('query.review_status', null)
                ->where('query.referral', null));
    }

    public function test_bulk_selectable_ids_follow_the_active_filter(): void
    {
        $selected = $this->answer(['user_id' => $this->sender('Budi Santoso', 'budi@example.test')]);
        $this->answer(['user_id' => $this->sender('Nadia Putri', 'nadia@example.test')]);

        $this->actingAs($this->owner)
            ->get($this->showPath(['search' => 'Budi']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectableSubmissionIds', fn (Collection $ids): bool => $ids->all() === [$selected->id])
                ->where('bulkSelectableTotal', 1));
    }

    public function test_page_beyond_last_page_is_clamped_to_filtered_result(): void
    {
        $this->answer();

        $this->actingAs($this->owner)
            ->get($this->showPath(['search' => 'tidak-ada', 'page' => '5']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 0)
                ->where('submissionsPagination.current_page', 1)
                ->where('submissionsPagination.last_page', 1));
    }

    private function sender(string $name, string $email): string
    {
        return User::factory()->create(['name' => $name, 'email' => $email])->id;
    }

    /** @param array<string, string> $query */
    private function showPath(array $query = []): string
    {
        return route('dashboard.events.forms.show', [
            'event' => $this->event,
            'form' => $this->form,
            'tab' => 'jawaban',
            ...$query,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function answer(array $overrides = []): FormAnswer
    {
        return FormAnswer::factory()->create(array_merge([
            'form_id' => $this->form->id,
            'answers' => ['full_name' => 'Peserta'],
        ], $overrides));
    }

    private function code(string $code): ReferralCode
    {
        [$prefix, $body] = explode('-', $code);

        return ReferralCode::query()->create([
            'form_id' => $this->form->id,
            'code' => $code,
            'prefix' => $prefix,
            'body' => $body,
            'generation_mode' => 'manual',
            'attribution_name' => 'Komunitas Udinus',
            'usage_limit' => null,
            'usage_count' => 0,
            'is_active' => true,
        ]);
    }

    private function redeem(ReferralCode $code, FormAnswer $answer): ReferralRedemption
    {
        $code->increment('usage_count');

        return ReferralRedemption::query()->create([
            'referral_code_id' => $code->id,
            'form_id' => $this->form->id,
            'form_answer_id' => $answer->id,
            'user_id' => $answer->user_id,
            'participant_snapshot' => ['name' => 'Peserta', 'email' => 'peserta@example.test', 'members' => []],
            'attribution_snapshot' => [
                'assigned_user_id' => null,
                'assigned_name' => null,
                'attribution_name' => $code->attribution_name,
                'code' => $code->code,
            ],
            'redeemed_at' => now(),
            'status' => ReferralRedemption::STATUS_VALID,
        ]);
    }
}

<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAccessStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormMultipleSubmissionTest extends TestCase
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

    private function form(array $metadata = []): array
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDay(),
            'quota' => 100,
            'registered_count' => 0,
        ]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'metadata' => array_merge([
                'purpose' => 'feedback',
                'registration_mode' => 'single',
            ], $metadata),
        ]);
        FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'full_name',
            'label' => 'Full Name',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => ['required' => true]],
        ]);

        return [$event, $form];
    }

    private function submitPath(Event $event, Form $form): string
    {
        return route('dashboard.forms.submission', ['event' => $event, 'form' => $form], false);
    }

    private function fillPath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false);
    }

    public function test_enabled_form_allows_two_total_submissions_and_blocks_the_third(): void
    {
        [$event, $form] = $this->form([
            'allow_multiple_submissions' => true,
            'max_submissions' => 2,
        ]);
        $member = $this->member();

        $this->actingAs($member)->post($this->submitPath($event, $form), ['full_name' => 'First'])->assertRedirect();
        $this->actingAs($member)->post($this->submitPath($event, $form), ['full_name' => 'Second'])->assertRedirect();

        $this->assertSame(2, FormAnswer::query()->where('form_id', $form->id)->where('user_id', $member->id)->count());
        $this->actingAs($member)
            ->get($this->fillPath($event, $form))
            ->assertInertia(fn ($page) => $page->where('accessStatus', FormAccessStatus::AlreadySubmitted->value));
    }

    public function test_enabled_form_defaults_to_two_total_submissions(): void
    {
        [$event, $form] = $this->form(['allow_multiple_submissions' => true]);
        $member = $this->member();

        $this->actingAs($member)->post($this->submitPath($event, $form), ['full_name' => 'First'])->assertRedirect();
        $this->actingAs($member)->post($this->submitPath($event, $form), ['full_name' => 'Second'])->assertRedirect();

        $this->assertSame(2, FormAnswer::query()->where('form_id', $form->id)->where('user_id', $member->id)->count());
    }

    public function test_rejected_submission_does_not_consume_repeated_submission_capacity(): void
    {
        [$event, $form] = $this->form([
            'allow_multiple_submissions' => true,
            'max_submissions' => 2,
        ]);
        $member = $this->member();
        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'review_status' => FormAnswerReviewStatus::Rejected,
            'answers' => ['full_name' => 'Rejected'],
        ]);

        $this->actingAs($member)->post($this->submitPath($event, $form), ['full_name' => 'First'])->assertRedirect();
        $this->actingAs($member)->post($this->submitPath($event, $form), ['full_name' => 'Second'])->assertRedirect();

        $this->assertSame(3, FormAnswer::query()->where('form_id', $form->id)->where('user_id', $member->id)->count());
    }

    public function test_database_allows_multiple_active_rows_for_the_new_application_limit(): void
    {
        [$event, $form] = $this->form([
            'allow_multiple_submissions' => true,
            'max_submissions' => 2,
        ]);
        $member = $this->member();

        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $member->id]);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $member->id]);

        $this->assertSame(2, FormAnswer::query()->where('form_id', $form->id)->where('user_id', $member->id)->count());
    }
}

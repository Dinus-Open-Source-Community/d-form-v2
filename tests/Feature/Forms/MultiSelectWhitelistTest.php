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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-select answers obey the same canonical option whitelist as checkbox:
 * the ticket invariant must hold for every array-valued choice field.
 */
class MultiSelectWhitelistTest extends TestCase
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

    private function formWithMultiSelect(array $metadata): array
    {
        $event = $this->openEvent();
        $form  = Form::factory()->create([
            'event_id'    => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at'   => now()->addDays(30),
        ]);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'selectInput',
            'name'       => 'topics',
            'label'      => 'Topics',
            'order'      => 1,
            'metadata'   => $metadata,
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

    public function test_out_of_list_element_is_rejected_without_writing_a_row(): void
    {
        $member = $this->member();
        [$event, $form] = $this->formWithMultiSelect([
            'is_multiple' => true,
            'builderType' => 'dropdown',
            'rules'       => ['required' => true, 'in' => 'A,B'],
        ]);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['topics' => ['A', 'Z']])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('topics.1');

        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_in_list_elements_are_stored(): void
    {
        $member = $this->member();
        [$event, $form] = $this->formWithMultiSelect([
            'is_multiple' => true,
            'builderType' => 'dropdown',
            'rules'       => ['required' => true, 'in' => 'A,B'],
        ]);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['topics' => ['A', 'B']])
            ->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form], false));

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame(['A', 'B'], $answer->answers['topics']);
    }
}

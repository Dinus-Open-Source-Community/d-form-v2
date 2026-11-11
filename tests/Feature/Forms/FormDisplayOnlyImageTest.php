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
 * `image` is a display-only builder block on the client; the server must agree
 * so the block never lands in `form_answers.answers`.
 */
class FormDisplayOnlyImageTest extends TestCase
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

    private function openForm(Event $event): Form
    {
        return Form::factory()->create([
            'event_id'    => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at'   => now()->addDays(30),
        ]);
    }

    private function submitPath(Event $event, Form $form): string
    {
        return route('dashboard.forms.submission', ['event' => $event, 'form' => $form], false);
    }

    public function test_image_block_is_not_stored_in_answers_and_client_value_is_ignored(): void
    {
        $member = $this->member();
        $event  = $this->openEvent();
        $form   = $this->openForm($event);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => 'image_block',
            'label'      => 'Image',
            'order'      => 1,
            'metadata'   => [
                'type'        => 'text',
                'builderType' => 'image',
                'rules'       => [],
                'imageUrl'    => 'form-images/example.png',
            ],
        ]);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => 'full_name',
            'label'      => 'Full Name',
            'order'      => 2,
            'metadata'   => ['type' => 'text', 'rules' => ['required' => true]],
        ]);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [
                'image_block' => 'client supplied string',
                'full_name'   => 'Only Real Field',
            ])
            ->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form], false));

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();

        $this->assertSame(['full_name'], array_keys($answer->answers));
    }

    public function test_existing_submission_that_already_contains_an_image_key_stays_readable(): void
    {
        $event = $this->openEvent();
        $form  = $this->openForm($event);

        $legacyAnswer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'answers' => [
                'full_name'   => 'Legacy Person',
                'image_block' => 'legacy stored value',
            ],
        ]);

        $answers = $legacyAnswer->fresh()->answers;

        $this->assertSame('Legacy Person', $answers['full_name']);
        $this->assertSame('legacy stored value', $answers['image_block']);
    }
}

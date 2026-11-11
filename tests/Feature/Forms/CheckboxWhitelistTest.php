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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Checkbox answers are whitelisted from the canonical option source written by
 * the builder (`rules.in` + `optionChoices`) and from legacy `metadata.options`.
 */
class CheckboxWhitelistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * @return array<string, array{metadata: array<string, mixed>, allowed: list<string>, rejected: string}>
     */
    public static function checkboxOptionSources(): array
    {
        return [
            'builder rules.in + optionChoices' => [
                'metadata' => [
                    'type'          => 'checkbox',
                    'builderType'   => 'checkbox',
                    'is_multiple'   => true,
                    'rules'         => ['required' => true, 'in' => 'design,coding'],
                    'optionChoices' => [
                        ['id' => '1', 'type' => 'text', 'label' => 'design', 'imageUrl' => ''],
                        ['id' => '2', 'type' => 'text', 'label' => 'coding', 'imageUrl' => ''],
                    ],
                ],
                'allowed'  => ['design', 'coding'],
                'rejected' => 'hacking',
            ],
            'optionChoices only' => [
                'metadata' => [
                    'type'          => 'checkbox',
                    'builderType'   => 'checkbox',
                    'rules'         => ['required' => true],
                    'optionChoices' => [
                        ['label' => 'red'],
                        ['label' => 'blue'],
                    ],
                ],
                'allowed'  => ['red', 'blue'],
                'rejected' => 'green',
            ],
            'legacy metadata.options csv' => [
                'metadata' => [
                    'type'  => 'checkbox',
                    'rules' => ['required' => true],
                    'options' => 'red,blue',
                ],
                'allowed'  => ['red', 'blue'],
                'rejected' => 'green',
            ],
            'rules.in as array' => [
                'metadata' => [
                    'type'  => 'checkbox',
                    'rules' => ['required' => true, 'in' => ['alpha', 'beta']],
                ],
                'allowed'  => ['alpha', 'beta'],
                'rejected' => 'gamma',
            ],
        ];
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

    private function formWithCheckbox(array $metadata): array
    {
        $event = $this->openEvent();
        $form  = Form::factory()->create([
            'event_id'    => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at'   => now()->addDays(30),
        ]);

        FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'checkbox',
            'name'       => 'interests',
            'label'      => 'Interests',
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

    /**
     * @param  array<string, mixed>  $metadata
     */
    #[DataProvider('checkboxOptionSources')]
    public function test_value_outside_the_canonical_option_list_is_rejected(array $metadata, array $allowed, string $rejected): void
    {
        $member = $this->member();
        [$event, $form] = $this->formWithCheckbox($metadata);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), [
                'interests' => [$allowed[0], $rejected],
            ])
            ->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('interests.1');

        $this->assertDatabaseCount('form_answers', 0);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    #[DataProvider('checkboxOptionSources')]
    public function test_value_inside_the_canonical_option_list_is_stored(array $metadata, array $allowed, string $rejected): void
    {
        $member = $this->member();
        [$event, $form] = $this->formWithCheckbox($metadata);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['interests' => $allowed])
            ->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form], false));

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame($allowed, $answer->answers['interests']);
    }

    public function test_checkbox_without_any_option_source_still_accepts_arbitrary_strings(): void
    {
        $member = $this->member();
        [$event, $form] = $this->formWithCheckbox([
            'type'  => 'checkbox',
            'rules' => ['required' => true],
        ]);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['interests' => ['anything', 'goes']])
            ->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form], false));

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame(['anything', 'goes'], $answer->answers['interests']);
    }
}

<?php

namespace Tests\Feature\Forms;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\Form\RulesBuilder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormPriceFieldValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_two_priced_fields_are_rejected(): void
    {
        [$event, $form] = $this->registrationForm();
        $payload = ['fields' => [
            $this->field('Harga A', 95_000, 1),
            $this->field('Harga B', 120_000, 2),
        ]];

        $this->actingAs($this->admin())
            ->postJson($this->savePath($event, $form), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fields.1.metadata.price']);
    }

    public function test_a_single_priced_field_is_accepted(): void
    {
        [$event, $form] = $this->registrationForm();

        $this->actingAs($this->admin())
            ->post($this->savePath($event, $form), ['fields' => [$this->field('Payment', 95_000, 1)]])
            ->assertRedirect();

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $form->id,
            'name' => 'field_1',
        ]);
    }

    public function test_price_is_rejected_on_a_non_registration_form(): void
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'feedback'],
        ]);

        $this->actingAs($this->admin())
            ->postJson($this->savePath($event, $form), ['fields' => [$this->field('Payment', 95_000, 1)]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fields.0.metadata.price']);
    }

    public function test_zero_price_is_ignored(): void
    {
        [$event, $form] = $this->registrationForm();
        $payload = ['fields' => [
            $this->field('A', 0, 1),
            $this->field('B', 0, 2),
        ]];

        $this->actingAs($this->admin())
            ->post($this->savePath($event, $form), $payload)
            ->assertRedirect();
    }

    public function test_negative_price_is_rejected(): void
    {
        [$event, $form] = $this->registrationForm();

        $this->actingAs($this->admin())
            ->postJson($this->savePath($event, $form), ['fields' => [$this->field('Payment', -1, 1)]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fields.0.metadata.price']);
    }

    public function test_price_builder_type_never_produces_an_answer(): void
    {
        $field = new FormField(['metadata' => ['builderType' => 'price']]);

        $this->assertTrue(RulesBuilder::isDisplayOnlyField($field));
    }

    /** @return array{0: Event, 1: Form} */
    private function registrationForm(): array
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'registration', 'registration_mode' => 'single'],
        ]);

        return [$event, $form];
    }

    /** @return array<string, mixed> */
    private function field(string $label, int $price, int $order): array
    {
        return [
            'id' => (string) Str::uuid(),
            'label' => $label,
            'type' => 'input',
            'name' => 'field_' . $order,
            'order' => $order,
            'metadata' => [
                'type' => 'text',
                'builderType' => 'price',
                'price' => $price,
            ],
        ];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function savePath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form], false);
    }
}

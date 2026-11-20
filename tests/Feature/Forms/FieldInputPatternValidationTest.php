<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use App\Services\Form\RulesBuilder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Server-side strict input shapes for `input` field subtypes: number, tel, nik,
 * nim, and postal_code must be enforced on the submission boundary.
 */
class FieldInputPatternValidationTest extends TestCase
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

    public function test_explicit_format_rule_overrides_plain_text_field_type(): void
    {
        $event = $this->openEvent();
        $form = $this->openForm($event);

        $field = FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => 'wa',
            'label'      => 'Nomor WhatsApp',
            'order'      => 1,
            'metadata'   => ['type' => 'text', 'rules' => ['format' => 'tel']],
        ]);

        $rules = RulesBuilder::build(RulesBuilder::extractRulesFromFields(collect([$field])));

        $valid = Validator::make(['wa' => '081234567890'], $rules);
        $this->assertFalse($valid->fails(), (string) $valid->errors()->first());

        $invalid = Validator::make(['wa' => 'abc123'], $rules);
        $this->assertTrue($invalid->fails());
    }

    private function typedField(Form $form, string $subtype): FormField
    {
        return FormField::factory()->create([
            'form_id'    => $form->id,
            'input_type' => 'input',
            'name'       => $subtype,
            'label'      => ucfirst($subtype),
            'order'      => 1,
            'metadata'   => ['type' => $subtype, 'rules' => ['required' => true]],
        ]);
    }

    /** @return array{0: TestResponse, 1: Event, 2: Form} */
    private function submitTypedValue(string $subtype, mixed $value): array
    {
        $event = $this->openEvent();
        $form = $this->openForm($event);
        $this->typedField($form, $subtype);

        $response = $this->actingAs($this->member())->post(
            route('dashboard.forms.submission', ['event' => $event, 'form' => $form], false),
            [$subtype => $value],
        );

        return [$response, $event, $form];
    }

    private function assertSubmissionAccepted(string $subtype, string $value): void
    {
        [$response, $event, $form] = $this->submitTypedValue($subtype, $value);

        $response
            ->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form], false))
            ->assertSessionHasNoErrors();

        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame($value, (string) $answer->answers[$subtype]);
    }

    private function assertSubmissionRejected(string $subtype, string $value): void
    {
        [$response, $event, $form] = $this->submitTypedValue($subtype, $value);

        $response
            ->assertRedirect(route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false))
            ->assertSessionHasErrors($subtype);

        $this->assertSame(0, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_number_accepts_plain_digits_including_zero(): void
    {
        $this->assertSubmissionAccepted('number', '0');
        $this->assertSubmissionAccepted('number', '007');
    }

    public function test_number_rejects_sign_decimal_and_non_digit_values(): void
    {
        $this->assertSubmissionRejected('number', '-1');
        $this->assertSubmissionRejected('number', '1.5');
        $this->assertSubmissionRejected('number', '12a');
    }

    public function test_tel_accepts_leading_plus_62_spaces_and_dashes(): void
    {
        $this->assertSubmissionAccepted('tel', '+62 812-3456-7890');
        $this->assertSubmissionAccepted('tel', '0812 3456 7890');
        $this->assertSubmissionAccepted('tel', '(0812) 3456.7890');
    }

    public function test_tel_rejects_numbers_shorter_than_nine_digits(): void
    {
        $this->assertSubmissionRejected('tel', '0812');
        $this->assertSubmissionRejected('tel', '+62 812');
    }

    public function test_nik_requires_exactly_sixteen_digits(): void
    {
        $this->assertSubmissionAccepted('nik', '1234567890123456');
        $this->assertSubmissionRejected('nik', '123456789012345');
        $this->assertSubmissionRejected('nik', '12345678901234567');
    }

    public function test_nim_requires_five_to_twenty_digits(): void
    {
        $this->assertSubmissionAccepted('nim', '12345');
        $this->assertSubmissionAccepted('nim', '21120120140001');
        $this->assertSubmissionRejected('nim', '1234');
        $this->assertSubmissionRejected('nim', str_repeat('1', 21));
    }

    public function test_postal_code_requires_exactly_five_digits(): void
    {
        $this->assertSubmissionAccepted('postal_code', '50275');
        $this->assertSubmissionRejected('postal_code', '5027');
        $this->assertSubmissionRejected('postal_code', '502755');
    }

    public function test_invalid_value_fails_with_422_and_a_field_scoped_indonesian_error(): void
    {
        $event = $this->openEvent();
        $form = $this->openForm($event);
        $field = $this->typedField($form, 'nik');

        $rules = RulesBuilder::build(RulesBuilder::extractRulesFromFields(collect([$field])));
        $validator = Validator::make(['nik' => '123456789012345'], $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('nik', $validator->errors()->toArray());

        try {
            $validator->validate();
            $this->fail('An invalid NIK must throw a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(422, $exception->status);
            $this->assertSame(['nik'], array_keys($exception->errors()));
            $this->assertSame('NIK harus 16 angka.', $exception->errors()['nik'][0]);
        }
    }

    public function test_builder_accepts_the_new_metadata_subtypes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        $fields = [];
        foreach (['nik', 'nim', 'postal_code'] as $index => $subtype) {
            $fields[] = [
                'id'       => (string) Str::uuid(),
                'label'    => ucfirst($subtype),
                'type'     => 'input',
                'name'     => $subtype,
                'order'    => $index + 1,
                'metadata' => ['type' => $subtype, 'rules' => []],
            ];
        }

        $this->actingAs($admin)
            ->post(route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form], false), ['fields' => $fields])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        foreach (['nik', 'nim', 'postal_code'] as $subtype) {
            $this->assertDatabaseHas('form_fields', [
                'form_id'    => $form->id,
                'name'       => $subtype,
                'input_type' => 'input',
            ]);
        }
    }
}

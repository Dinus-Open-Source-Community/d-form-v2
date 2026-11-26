<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\ReferralCode;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FormPriceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    public function test_single_submission_snapshots_the_discounted_price(): void
    {
        [$event, $form] = $this->singleForm();
        $code = $this->code($form, 'UDIN-AB12', ['discount_type' => 'percentage', 'discount_value' => 20]);

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta', 'referral_code' => 'UDIN-AB12'])
            ->assertRedirect();

        $this->assertSame($this->expectedPricing($code, 19_000, 76_000), $this->answer($form)->pricing);
    }

    public function test_single_submission_without_a_code_snapshots_the_base_price(): void
    {
        [$event, $form] = $this->singleForm();

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta'])
            ->assertRedirect();

        $this->assertSame([
            'base' => 95_000,
            'discount_type' => null,
            'discount_value' => null,
            'discount' => 0,
            'final' => 95_000,
            'referral_code_id' => null,
            'referral_code' => null,
        ], $this->answer($form)->pricing);
    }

    public function test_unpriced_form_leaves_pricing_null(): void
    {
        [$event, $form] = $this->singleForm(withPrice: false);

        $this->actingAs($this->member())
            ->post($this->submitPath($event, $form), ['full_name' => 'Peserta'])
            ->assertRedirect();

        $this->assertNull($this->answer($form)->pricing);
    }

    public function test_team_submission_snapshots_every_row(): void
    {
        [$leader, $teammate, $event, $form] = $this->teamForm();
        $code = $this->code($form, 'UDIN-AB12', ['discount_type' => 'fixed', 'discount_value' => 25_000]);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Ketua',
                'team_member_emails' => [$teammate->email],
                'referral_code' => 'UDIN-AB12',
            ])
            ->assertRedirect();

        $rows = FormAnswer::query()->where('form_id', $form->id)->get();
        $expected = $this->expectedPricing($code, 25_000, 70_000);

        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertSame($expected, $row->pricing);
        }
    }

    public function test_bundle_submission_snapshots_every_row(): void
    {
        [$leader, $event, $form] = $this->bundleForm(['guest@example.test']);
        $code = $this->code($form, 'UDIN-AB12', ['discount_type' => 'percentage', 'discount_value' => 10]);

        $this->actingAs($leader)
            ->post($this->submitPath($event, $form), [
                'full_name' => 'Ketua',
                'bundle__full_name__0' => 'Anggota',
                'team_member_emails' => ['guest@example.test'],
                'referral_code' => 'UDIN-AB12',
            ])
            ->assertRedirect();

        $rows = FormAnswer::query()->where('form_id', $form->id)->get();
        $expected = $this->expectedPricing($code, 9_500, 85_500);

        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertSame($expected, $row->pricing);
        }
    }

    /** @return array<string, mixed> */
    private function expectedPricing(ReferralCode $code, int $discount, int $final): array
    {
        return [
            'base' => 95_000,
            'discount_type' => $code->discount_type?->value,
            'discount_value' => $code->discount_value,
            'discount' => $discount,
            'final' => $final,
            'referral_code_id' => $code->id,
            'referral_code' => $code->code,
        ];
    }

    private function answer(Form $form): FormAnswer
    {
        return FormAnswer::query()->where('form_id', $form->id)->sole();
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
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
            'quota' => 100,
            'registered_count' => 0,
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function openForm(Event $event, array $metadata): Form
    {
        return Form::factory()->create([
            'event_id' => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
            'referral_enabled' => true,
            'metadata' => $metadata,
        ]);
    }

    /** @return array{0: Event, 1: Form} */
    private function singleForm(bool $withPrice = true): array
    {
        $event = $this->openEvent();
        $form = $this->openForm($event, ['purpose' => 'registration', 'registration_mode' => 'single']);

        if ($withPrice) {
            $this->priceField($form);
        }

        $this->textField($form);

        return [$event, $form];
    }

    /** @return array{0: User, 1: User, 2: Event, 3: Form} */
    private function teamForm(): array
    {
        $leader = $this->member();
        $teammate = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event, [
            'purpose' => 'registration',
            'registration_mode' => 'team',
            'team_size' => 2,
        ]);
        $this->priceField($form);
        $this->textField($form);

        return [$leader, $teammate, $event, $form];
    }

    /** @param list<string> $guestEmails @return array{0: User, 1: Event, 2: Form} */
    private function bundleForm(array $guestEmails): array
    {
        $leader = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event, [
            'purpose' => 'registration',
            'registration_mode' => 'bundle',
            'max_team_size' => count($guestEmails) + 1,
        ]);
        $this->priceField($form);
        $this->textField($form);

        return [$leader, $event, $form];
    }

    private function priceField(Form $form): FormField
    {
        return FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'payment',
            'label' => 'Payment',
            'order' => 1,
            'metadata' => ['builderType' => 'price', 'type' => 'text', 'price' => 95_000],
        ]);
    }

    private function textField(Form $form): FormField
    {
        return FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'full_name',
            'label' => 'Full Name',
            'order' => 2,
            'metadata' => ['type' => 'text', 'rules' => ['required' => true]],
        ]);
    }

    private function submitPath(Event $event, Form $form): string
    {
        return route('dashboard.forms.submission', ['event' => $event, 'form' => $form], false);
    }

    /** @param array<string, mixed> $overrides */
    private function code(Form $form, string $code, array $overrides = []): ReferralCode
    {
        [$prefix, $body] = explode('-', $code);

        return ReferralCode::query()->create(array_merge([
            'form_id' => $form->id,
            'code' => $code,
            'prefix' => $prefix,
            'body' => $body,
            'generation_mode' => 'manual',
            'attribution_name' => 'Komunitas Udinus',
            'usage_limit' => null,
            'usage_count' => 0,
            'is_active' => true,
        ], $overrides));
    }
}

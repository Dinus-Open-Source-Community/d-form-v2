<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use App\Models\User;
use App\Services\Form\FormPurposeRules;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReferralCodeCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->member = User::factory()->create();
        $this->member->assignRole('member');
    }

    public function test_valid_code_is_accepted(): void
    {
        [$event, $form] = $this->registrationForm();
        $this->code($form, 'UDIN-AB12');

        $this->check($event, $form, 'udin-ab12')
            ->assertOk()
            ->assertExactJson(['valid' => true, 'message' => null, 'price' => null]);
    }

    public function test_unknown_code_reports_not_found(): void
    {
        [$event, $form] = $this->registrationForm();

        $this->check($event, $form, 'UDIN-ZZ99')
            ->assertExactJson(['valid' => false, 'message' => 'Kode referral tidak ditemukan.', 'price' => null]);
    }

    public function test_inactive_code_is_reported(): void
    {
        [$event, $form] = $this->registrationForm();
        $this->code($form, 'UDIN-AB12', ['is_active' => false]);

        $this->check($event, $form, 'UDIN-AB12')
            ->assertExactJson(['valid' => false, 'message' => 'Kode referral tidak aktif.', 'price' => null]);
    }

    public function test_code_before_its_start_is_reported(): void
    {
        [$event, $form] = $this->registrationForm();
        $this->code($form, 'UDIN-AB12', ['starts_at' => now()->addDay()]);

        $this->check($event, $form, 'UDIN-AB12')
            ->assertExactJson(['valid' => false, 'message' => 'Kode referral belum aktif.', 'price' => null]);
    }

    public function test_expired_code_is_reported(): void
    {
        [$event, $form] = $this->registrationForm();
        $this->code($form, 'UDIN-AB12', ['expires_at' => now()->subMinute()]);

        $this->check($event, $form, 'UDIN-AB12')
            ->assertExactJson(['valid' => false, 'message' => 'Kode referral sudah kedaluwarsa.', 'price' => null]);
    }

    public function test_exhausted_quota_is_reported(): void
    {
        [$event, $form] = $this->registrationForm();
        $this->code($form, 'UDIN-AB12', ['usage_limit' => 2, 'usage_count' => 2]);

        $this->check($event, $form, 'UDIN-AB12')
            ->assertExactJson(['valid' => false, 'message' => 'Kuota kode referral sudah habis.', 'price' => null]);
    }

    public function test_disabled_form_referral_is_reported(): void
    {
        [$event, $form] = $this->registrationForm(['referral_enabled' => false]);
        $this->code($form, 'UDIN-AB12');

        $this->check($event, $form, 'UDIN-AB12')
            ->assertExactJson(['valid' => false, 'message' => 'Kode referral tidak tersedia untuk form ini.', 'price' => null]);
    }

    public function test_empty_code_is_not_an_error(): void
    {
        [$event, $form] = $this->registrationForm();

        foreach (['', '   ', null] as $raw) {
            $this->check($event, $form, $raw)
                ->assertOk()
                ->assertExactJson(['valid' => false, 'message' => null, 'price' => null]);
        }
    }

    public function test_check_never_writes_redemption_or_usage(): void
    {
        [$event, $form] = $this->registrationForm();
        $code = $this->code($form, 'UDIN-AB12');

        $this->check($event, $form, 'UDIN-AB12')
            ->assertExactJson(['valid' => true, 'message' => null, 'price' => null]);

        $this->assertSame(0, ReferralRedemption::query()->count());
        $this->assertSame(0, $code->fresh()->usage_count);
    }

    public function test_priced_form_quotes_the_discount_for_a_valid_code(): void
    {
        [$event, $form] = $this->registrationForm();
        $this->priceField($form, 95_000);
        $this->code($form, 'UDIN-AB12', ['discount_type' => 'percentage', 'discount_value' => 20]);

        $this->check($event, $form, 'UDIN-AB12')
            ->assertExactJson([
                'valid' => true,
                'message' => null,
                'price' => [
                    'base' => 95_000,
                    'discount_type' => 'percentage',
                    'discount_value' => 20,
                    'discount' => 19_000,
                    'final' => 76_000,
                ],
            ]);
    }

    public function test_valid_code_without_discount_quotes_the_base_price(): void
    {
        [$event, $form] = $this->registrationForm();
        $this->priceField($form, 95_000);
        $this->code($form, 'UDIN-AB12');

        $this->check($event, $form, 'UDIN-AB12')
            ->assertExactJson([
                'valid' => true,
                'message' => null,
                'price' => [
                    'base' => 95_000,
                    'discount_type' => null,
                    'discount_value' => null,
                    'discount' => 0,
                    'final' => 95_000,
                ],
            ]);
    }

    public function test_unpriced_form_never_quotes_a_price(): void
    {
        [$event, $form] = $this->registrationForm();
        $this->code($form, 'UDIN-AB12', ['discount_type' => 'fixed', 'discount_value' => 20_000]);

        $this->check($event, $form, 'UDIN-AB12')
            ->assertExactJson(['valid' => true, 'message' => null, 'price' => null]);
    }

    public function test_referral_position_is_normalized_on_write(): void
    {
        $preserved = FormPurposeRules::apply(['purpose' => 'registration', 'referral_position' => '3f2a-9c1b']);
        $this->assertSame('3f2a-9c1b', $preserved['referral_position']);

        foreach (['start', 'end'] as $sentinel) {
            $metadata = FormPurposeRules::apply(['purpose' => 'registration', 'referral_position' => $sentinel]);
            $this->assertSame($sentinel, $metadata['referral_position']);
        }

        foreach (['', '   ', 'has space', 'a<b', str_repeat('a', 65), 42, null] as $invalid) {
            $metadata = FormPurposeRules::apply(['purpose' => 'registration', 'referral_position' => $invalid]);
            $this->assertSame('end', $metadata['referral_position']);
        }
    }

    private function check(Event $event, Form $form, ?string $code): TestResponse
    {
        return $this->actingAs($this->member)->postJson(
            route('dashboard.forms.referral-check', ['event' => $event, 'form' => $form], false),
            ['code' => $code],
        );
    }

    private function priceField(Form $form, int $price): void
    {
        $form->formFields()->create([
            'input_type' => 'input',
            'label' => 'Payment',
            'name' => 'payment',
            'order' => 1,
            'metadata' => ['builderType' => 'price', 'type' => 'text', 'price' => $price],
        ]);
    }

    /** @return array{0: Event, 1: Form} */
    private function registrationForm(array $overrides = []): array
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
            'quota' => 100,
            'registered_count' => 0,
        ]);

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
            'referral_enabled' => true,
            'metadata' => [
                'purpose' => 'registration',
                'registration_mode' => 'single',
            ],
        ]);

        if ($overrides !== []) {
            $form->forceFill($overrides)->save();
        }

        return [$event, $form];
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

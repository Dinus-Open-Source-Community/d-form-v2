<?php

namespace Tests\Feature\Forms;

use App\Enums\ReferralDiscountType;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\ReferralCode;
use App\Services\Form\FormPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormPriceServiceTest extends TestCase
{
    use RefreshDatabase;

    private FormPriceService $prices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prices = app(FormPriceService::class);
    }

    public function test_quote_is_null_without_a_base_price(): void
    {
        $this->assertNull($this->prices->quote(null, $this->code('percentage', 20)));
    }

    public function test_percentage_discount_rounds_down(): void
    {
        $this->assertSame([
            'base' => 95_000,
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'discount' => 19_000,
            'final' => 76_000,
        ], $this->prices->quote(95_000, $this->code('percentage', 20)));
    }

    public function test_percentage_discount_never_keeps_a_fraction(): void
    {
        $quote = $this->prices->quote(999, $this->code('percentage', 33));

        $this->assertSame(329, $quote['discount']);
        $this->assertSame(670, $quote['final']);
    }

    public function test_full_percentage_discount_ends_at_zero(): void
    {
        $quote = $this->prices->quote(50_000, $this->code('percentage', 100));

        $this->assertSame(50_000, $quote['discount']);
        $this->assertSame(0, $quote['final']);
    }

    public function test_fixed_discount_is_clamped_to_the_base(): void
    {
        $quote = $this->prices->quote(95_000, $this->code('fixed', 200_000));

        $this->assertSame(95_000, $quote['discount']);
        $this->assertSame(0, $quote['final']);
    }

    public function test_fixed_discount_lowers_the_price(): void
    {
        $quote = $this->prices->quote(95_000, $this->code('fixed', 20_000));

        $this->assertSame(20_000, $quote['discount']);
        $this->assertSame(75_000, $quote['final']);
    }

    public function test_code_without_discount_keeps_the_base_price(): void
    {
        $quote = $this->prices->quote(95_000, $this->code(null, null));

        $this->assertSame(0, $quote['discount']);
        $this->assertSame(95_000, $quote['final']);
    }

    public function test_missing_code_keeps_the_base_price(): void
    {
        $quote = $this->prices->quote(95_000, null);

        $this->assertSame(0, $quote['discount']);
        $this->assertSame(95_000, $quote['final']);
    }

    public function test_base_price_reads_the_first_priced_field_in_order(): void
    {
        $form = Form::factory()->create();

        $this->field($form, 1, ['price' => 0]);
        $this->field($form, 2, ['price' => 95_000]);
        $this->field($form, 3, ['price' => 120_000]);

        $this->assertSame(95_000, $this->prices->basePrice($form));
    }

    public function test_price_metadata_on_another_builder_type_is_ignored(): void
    {
        $form = Form::factory()->create();
        $this->field($form, 1, ['builderType' => 'heading', 'price' => 95_000]);

        $this->assertNull($this->prices->basePrice($form));
    }

    public function test_base_price_is_null_when_no_field_is_priced(): void
    {
        $form = Form::factory()->create();
        $this->field($form, 1, []);

        $this->assertNull($this->prices->basePrice($form));
    }

    public function test_snapshot_writes_the_same_pricing_to_leader_and_members(): void
    {
        $form = Form::factory()->create();
        $leader = FormAnswer::factory()->create(['form_id' => $form->id]);
        $members = FormAnswer::factory()->count(2)->create(['form_id' => $form->id]);

        $code = $this->code('percentage', 20);
        $code->id = 'referral-1';
        $code->code = 'UDIN-AB12';

        $this->prices->snapshot($leader, $members, $this->prices->quote(95_000, $code), $code);

        $expected = [
            'base' => 95_000,
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'discount' => 19_000,
            'final' => 76_000,
            'referral_code_id' => 'referral-1',
            'referral_code' => 'UDIN-AB12',
        ];

        $this->assertSame($expected, $leader->fresh()->pricing);

        foreach ($members as $member) {
            $this->assertSame($expected, $member->fresh()->pricing);
        }
    }

    public function test_snapshot_does_nothing_without_a_quote(): void
    {
        $form = Form::factory()->create();
        $leader = FormAnswer::factory()->create(['form_id' => $form->id]);

        $this->prices->snapshot($leader, [], null, null);

        $this->assertNull($leader->fresh()->pricing);
    }

    private function code(?string $type, ?int $value): ReferralCode
    {
        $code = new ReferralCode();
        $code->discount_type = $type === null ? null : ReferralDiscountType::from($type);
        $code->discount_value = $value;

        return $code;
    }

    /** @param array<string, mixed> $meta */
    private function field(Form $form, int $order, array $meta): void
    {
        $form->formFields()->create([
            'input_type' => 'input',
            'label' => 'Field ' . $order,
            'name' => 'field_' . $order,
            'order' => $order,
            'metadata' => ['builderType' => 'price', ...$meta],
        ]);
    }
}

<?php

namespace Tests\Unit\Form;

use App\Models\FormField;
use App\Services\Form\RulesBuilder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\In;
use Tests\TestCase;

/**
 * Option whitelists must be emitted as array-form `Rule::in`, so labels that
 * contain the CSV separator (`,`) or the rule separator (`|`) stay single
 * values instead of widening the whitelist or injecting rules.
 */
class RulesBuilderOptionWhitelistTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, array<int, mixed>>
     */
    private function builtRules(string $inputType, array $metadata): array
    {
        $field = FormField::factory()->make([
            'name'       => 'choices',
            'input_type' => $inputType,
            'metadata'   => $metadata,
        ]);

        return RulesBuilder::build(RulesBuilder::extractRulesFromFields(collect([$field])));
    }

    public function test_comma_and_pipe_labels_stay_one_quoted_value_per_element(): void
    {
        $rules = $this->builtRules('checkbox', [
            'rules'         => ['required' => true],
            'optionChoices' => [
                ['label' => 'a,b'],
                ['label' => 'x|required'],
            ],
        ]);

        $inRule = $rules['choices.*'][1];

        $this->assertInstanceOf(In::class, $inRule);
        $this->assertSame('in:"a,b","x|required"', (string) $inRule);

        // A split label would make `a` acceptable; the array rule must reject it.
        $this->assertTrue(Validator::make(['choices' => ['a']], $rules)->fails());
        $this->assertFalse(Validator::make(['choices' => ['a,b']], $rules)->fails());
        $this->assertFalse(Validator::make(['choices' => ['x|required']], $rules)->fails());
    }

    public function test_multi_select_whitelists_each_element(): void
    {
        $rules = $this->builtRules('selectInput', [
            'is_multiple' => true,
            'rules'       => ['required' => true, 'in' => 'A,B'],
        ]);

        $this->assertSame(['required', 'array'], $rules['choices']);
        $this->assertInstanceOf(In::class, $rules['choices.*'][1]);
        $this->assertSame('in:"A","B"', (string) $rules['choices.*'][1]);

        $this->assertTrue(Validator::make(['choices' => ['A', 'Z']], $rules)->fails());
        $this->assertFalse(Validator::make(['choices' => ['A', 'B']], $rules)->fails());
    }

    public function test_multi_select_without_option_list_still_accepts_any_string(): void
    {
        $rules = $this->builtRules('selectInput', [
            'is_multiple' => true,
            'rules'       => ['required' => true],
        ]);

        $this->assertSame(['string'], $rules['choices.*']);
        $this->assertFalse(Validator::make(['choices' => ['anything', 'goes']], $rules)->fails());
    }

    public function test_radio_in_rule_uses_array_form(): void
    {
        $rules = $this->builtRules('radio', [
            'rules' => ['required' => true, 'in' => 'x|required'],
        ]);

        $inRule = collect($rules['choices'])->first(
            static fn (mixed $rule): bool => $rule instanceof In,
        );

        $this->assertInstanceOf(In::class, $inRule);
        $this->assertSame('in:"x|required"', (string) $inRule);
        $this->assertTrue(Validator::make(['choices' => 'x'], $rules)->fails());
        $this->assertFalse(Validator::make(['choices' => 'x|required'], $rules)->fails());
    }
}

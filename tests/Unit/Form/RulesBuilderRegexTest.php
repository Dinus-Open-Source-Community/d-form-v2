<?php

namespace Tests\Unit\Form;

use App\Services\Form\RulesBuilder;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RulesBuilderRegexTest extends TestCase
{
    /** @return array<string, array<int, string>> */
    private function rulesFor(string $pattern): array
    {
        return RulesBuilder::build([
            'code' => ['required' => true, 'regex' => $pattern],
        ]);
    }

    public function test_backslash_slash_is_escaped_in_rule_string(): void
    {
        $rules = $this->rulesFor('^[a-z]+/[0-9]+$');

        $this->assertContains('regex:/^[a-z]+\/[0-9]+$/', $rules['code']);
    }

    public function test_regex_with_slash_validates_matching_value(): void
    {
        $validator = Validator::make(['code' => 'abc/12'], $this->rulesFor('^[a-z]+/[0-9]+$'));

        $this->assertFalse($validator->fails());
    }

    public function test_regex_with_slash_rejects_non_matching_value(): void
    {
        $validator = Validator::make(['code' => 'abc-12'], $this->rulesFor('^[a-z]+/[0-9]+$'));

        $this->assertTrue($validator->fails());
    }

    public function test_alternation_regex_is_not_split(): void
    {
        $validator = Validator::make(['code' => 'beta'], $this->rulesFor('^(alpha|beta)$'));

        $this->assertFalse($validator->fails());
    }
}

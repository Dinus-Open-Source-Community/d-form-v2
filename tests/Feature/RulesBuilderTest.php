<?php

namespace Tests\Feature;

use App\Models\FormField;
use App\Services\Form\RulesBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class RulesBuilderTest extends TestCase
{
    #[Test]
    #[TestDox('Tes RulesBuilder::extractRulesFromFields')]
    public function test_extract_rules_from_fields(): void
    {
        $expectedRawRules = [
                'username' => ['required' => 1, 'min' => 10, 'max' => 255, 'regex' => '^adalah$'],
                'email'    => ['required' => 1, 'min' => 10, 'max' => 255, 'email' => 1],
                'bio'      => ['required' => null],
                'start_date' => ['max_date' => '2026-10-10'],
                'image'    => ['required' => 1, 'max_size' => 2048, 'mimes' => 'jpg'],
            ];

        $expectedBuiltRules = [
            'username' => [
                'required',
                'min:10',
                'max:255',
                'regex:/^adalah$/',
            ],
            'email' => [
                'required',
                'min:10',
                'max:255',
                'email'
            ],
            'bio' => [
                'nullable',
            ],
            'start_date' => [
                'nullable',
                'before_or_equal:2026-10-10',
            ],
            'image' => [
                'required',
                'mimes:jpg',
                'max:2048',
            ],
        ];

        $fields = collect([
            FormField::factory()->make(['name' => 'username']),
            FormField::factory()->emailInput()->make(['name' => 'email']),
            FormField::factory()->textArea()->make(['name' => 'bio']),
            FormField::factory()->datePicker()->make(['name' => 'start_date']),
            FormField::factory()->fileUpload()->make(['name' => 'image']),
        ]);

        $result = RulesBuilder::extractRulesFromFields($fields);

        $this->assertEquals($expectedRawRules, $result);

        $result = RulesBuilder::build($result);

        $this->assertEquals($expectedBuiltRules, $result);
    }
}

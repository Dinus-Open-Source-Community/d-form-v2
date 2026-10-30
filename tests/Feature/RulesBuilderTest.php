<?php

namespace Tests\Feature;

use App\Models\FormField;
use App\Rules\SafeUploadFile;
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
                'image'    => ['required' => true, 'max_size' => 2048, 'mimes' => 'jpg', 'is_file' => true],
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

        $builtRules = RulesBuilder::build($result);
        $fileRules = $builtRules['image'];
        unset($builtRules['image']);

        $this->assertEquals($expectedBuiltRules, $builtRules);

        // Field file memakai SafeUploadFile, bukan string mimes:/max: lagi.
        $this->assertSame(['required', 'file'], array_slice($fileRules, 0, 2));
        $this->assertCount(1, array_filter($fileRules, static fn (mixed $rule): bool => $rule instanceof SafeUploadFile));
        $this->assertNotContains('mimes:jpg', $fileRules);
        $this->assertNotContains('max:2048', $fileRules);
    }
}

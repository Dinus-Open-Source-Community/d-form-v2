<?php

namespace Tests\Feature;

use App\Services\Recruitment\OprecFormDefinition;
use Database\Seeders\OprecFormSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OprecFormSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_form_with_fifteen_contract_fields(): void
    {
        $this->seed([OprecFormSeeder::class]);

        $form = app(OprecFormDefinition::class)->requiredForm();

        $this->assertSame('Formulir Open Recruitment', $form->title);
        $names = $form->formFields()->orderBy('order')->pluck('name')->all();
        $this->assertSame([
            'full_name', 'nim', 'semester', 'phone', 'personal_email', 'student_email',
            'instagram_username', 'primary_division_id', 'secondary_division_id',
            'portfolio_type', 'portfolio_url', 'portfolio_file', 'cv',
            'instagram_follow_proof', 'twibbon_url',
        ], $names);
    }

    public function test_every_field_carries_top_level_step(): void
    {
        $this->seed([OprecFormSeeder::class]);

        $form = app(OprecFormDefinition::class)->requiredForm();
        $fields = $form->formFields()->get();

        foreach ($fields as $field) {
            $metadata = $field->metadata;

            $this->assertArrayHasKey('step', $metadata, "Field {$field->name} has no top-level step.");
            $this->assertIsInt($metadata['step'], "Field {$field->name} step is not an integer.");
            $this->assertGreaterThanOrEqual(1, $metadata['step']);
            $this->assertLessThanOrEqual(3, $metadata['step']);

            $rules = $metadata['rules'] ?? [];
            $this->assertArrayNotHasKey('step', $rules, "Field {$field->name} still nests step inside rules.");
        }

        $distribution = $fields
            ->groupBy(fn ($field) => (int) $field->metadata['step'])
            ->map->count()
            ->all();
        ksort($distribution);

        $this->assertSame([1 => 7, 2 => 2, 3 => 6], $distribution);
    }

    public function test_rerun_does_not_duplicate_fields(): void
    {
        $this->seed([OprecFormSeeder::class]);
        $this->seed([OprecFormSeeder::class]);

        $form = app(OprecFormDefinition::class)->requiredForm();

        $this->assertSame(15, $form->formFields()->count());
    }
}

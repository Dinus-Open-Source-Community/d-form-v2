<?php

namespace Tests\Feature;

use App\Services\Recruitment\OprecFormDefinition;
use Database\Seeders\EventSeeder;
use Database\Seeders\OprecFormSeeder;
use Database\Seeders\RecruitmentDivisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OprecFormDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_thirteen_fields_with_live_division_options(): void
    {
        $this->seed([EventSeeder::class, RecruitmentDivisionSeeder::class, OprecFormSeeder::class]);

        $definition = app(OprecFormDefinition::class);
        $fields = $definition->fieldsForDisplay();

        $this->assertCount(13, $fields);
        $byName = collect($fields)->keyBy('name');
        $expectedNames = \DB::table('recruitment_divisions')
            ->where('is_active', true)->orderBy('sort_order')->orderBy('name')
            ->pluck('name')->all();
        $this->assertSame($expectedNames, $byName['primary_division_id']['metadata']['options']);
        $this->assertSame($expectedNames, $byName['secondary_division_id']['metadata']['options']);
        $this->assertSame('radio', $byName['portfolio_type']['type']);
        $this->assertSame('fileUpload', $byName['cv']['type']);
    }

    public function test_division_map_resolves_names_to_ids(): void
    {
        $this->seed([RecruitmentDivisionSeeder::class]);

        $map = app(OprecFormDefinition::class)->activeDivisionMap();
        $first = \DB::table('recruitment_divisions')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->first();

        $this->assertSame($first->id, $map[$first->name]);
    }
}

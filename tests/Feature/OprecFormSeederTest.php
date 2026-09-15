<?php

namespace Tests\Feature;

use App\Services\Recruitment\OprecFormDefinition;
use Database\Seeders\EventSeeder;
use Database\Seeders\OprecFormSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OprecFormSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_form_with_thirteen_contract_fields(): void
    {
        $this->seed([EventSeeder::class, OprecFormSeeder::class]);

        $form = app(OprecFormDefinition::class)->requiredForm();

        $this->assertSame('Formulir Open Recruitment', $form->title);
        $names = $form->formFields()->orderBy('order')->pluck('name')->all();
        $this->assertSame([
            'full_name', 'nim', 'semester', 'phone', 'personal_email', 'student_email',
            'instagram_username', 'primary_division_id', 'secondary_division_id',
            'portfolio_type', 'portfolio_url', 'portfolio_file', 'cv',
        ], $names);
    }

    public function test_rerun_does_not_duplicate_fields(): void
    {
        $this->seed([EventSeeder::class, OprecFormSeeder::class]);
        $this->seed([OprecFormSeeder::class]);

        $form = app(OprecFormDefinition::class)->requiredForm();

        $this->assertSame(13, $form->formFields()->count());
    }
}

<?php

namespace Tests\Feature\Console;

use App\Models\Form;
use App\Models\FormAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MoveFormUploadsToPrivateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_without_moving_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $form = Form::factory()->create();
        $path = 'form-uploads/'.$form->id.'/legacy.pdf';
        Storage::disk('public')->put($path, 'legacy');

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'answers' => ['cv' => $path],
        ]);

        $this->artisan('forms:move-uploads-to-private', ['--dry-run' => true])
            ->expectsOutputToContain('Mode: DRY-RUN')
            ->expectsOutputToContain('WOULD move '.$path)
            ->assertSuccessful();

        Storage::disk('public')->assertExists($path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_write_moves_files_to_private_disk_and_keeps_answer_path(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $form = Form::factory()->create();
        $path = 'form-uploads/'.$form->id.'/legacy.pdf';
        Storage::disk('public')->put($path, 'legacy');

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'answers' => ['cv' => $path],
        ]);

        $this->artisan('forms:move-uploads-to-private', ['--write' => true])
            ->expectsOutputToContain('Mode: WRITE')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame($path, $answer->fresh()->answers['cv']);
    }
}

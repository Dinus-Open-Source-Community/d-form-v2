<?php

namespace Tests\Feature\Migrations;

use App\Enums\FormStatus;
use App\Models\Event;
use App\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `forms.status` wajib punya default `published` agar baris lama (dan insert
 * tanpa status eksplisit) tetap terhitung published, bukan draft.
 */
class FormStatusDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_forms_status_column_defaults_to_published_for_rows_inserted_without_status(): void
    {
        $event = Event::factory()->create();

        DB::table('forms')->insert([
            'id' => (string) Str::uuid(),
            'title' => 'Legacy Form',
            'description' => 'Legacy description',
            'visible_for' => json_encode(['public']),
            'closed_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'event_id' => $event->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            FormStatus::Published->value,
            DB::table('forms')->where('event_id', $event->id)->value('status'),
        );
    }

    public function test_model_casts_status_to_the_form_status_enum(): void
    {
        $form = Form::factory()->create();

        $this->assertSame(FormStatus::Published, $form->refresh()->status);
        $this->assertFalse($form->isDraft());

        $form->update(['status' => FormStatus::Draft]);

        $this->assertTrue($form->refresh()->isDraft());
    }

    public function test_event_and_status_composite_index_exists(): void
    {
        $this->assertTrue(
            Schema::hasIndex('forms', 'forms_event_id_status_index'),
            'The (event_id, status) index is required by the admin form list filter.',
        );
    }
}

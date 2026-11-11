<?php

namespace Tests\Feature\Migrations;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * L-3: the backfill migration must restore the legacy behaviour for archives
 * created before `archived_by_event_id` existed, without reviving children that
 * were archived independently.
 */
class ArchivedByEventBackfillTest extends TestCase
{
    use RefreshDatabase;

    private const ARCHIVE_TIME = '2026-01-02 03:04:05';

    private const INDEPENDENT_TIME = '2025-12-01 00:00:00';

    public function test_backfill_marks_rows_archived_together_with_their_event(): void
    {
        [$event, $form, $field] = $this->legacyArchive(self::ARCHIVE_TIME);

        $this->runBackfill();

        $this->assertSame($event->id, DB::table('forms')->where('id', $form->id)->value('archived_by_event_id'));
        $this->assertSame($event->id, DB::table('form_fields')->where('id', $field->id)->value('archived_by_event_id'));

        Event::withTrashed()->findOrFail($event->id)->restore();

        $this->assertNull(Form::withTrashed()->find($form->id)->deleted_at);
        $this->assertNull(FormField::withTrashed()->find($field->id)->deleted_at);
    }

    public function test_backfill_leaves_independently_archived_children_archived(): void
    {
        [$event, $form, $field] = $this->legacyArchive(self::ARCHIVE_TIME);

        $independentForm = Form::factory()->create(['event_id' => $event->id]);
        $independentField = FormField::factory()->create(['form_id' => $independentForm->id]);

        DB::table('forms')->where('id', $independentForm->id)->update(['deleted_at' => self::INDEPENDENT_TIME]);
        DB::table('form_fields')->where('id', $independentField->id)->update(['deleted_at' => self::INDEPENDENT_TIME]);

        $this->runBackfill();

        $this->assertNull(DB::table('forms')->where('id', $independentForm->id)->value('archived_by_event_id'));
        $this->assertNull(DB::table('form_fields')->where('id', $independentField->id)->value('archived_by_event_id'));

        Event::withTrashed()->findOrFail($event->id)->restore();

        $this->assertNull(Form::withTrashed()->find($form->id)->deleted_at);
        $this->assertSame(self::INDEPENDENT_TIME, DB::table('forms')->where('id', $independentForm->id)->value('deleted_at'));
        $this->assertSame(self::INDEPENDENT_TIME, DB::table('form_fields')->where('id', $independentField->id)->value('deleted_at'));
    }

    public function test_backfill_is_idempotent_and_does_not_overwrite_observer_markers(): void
    {
        [$event, $form, $field] = $this->legacyArchive(self::ARCHIVE_TIME);

        $this->runBackfill();
        $this->runBackfill();

        $this->assertSame($event->id, DB::table('forms')->where('id', $form->id)->value('archived_by_event_id'));
        $this->assertSame($event->id, DB::table('form_fields')->where('id', $field->id)->value('archived_by_event_id'));
        $this->assertSame(1, DB::table('forms')->where('event_id', $event->id)->whereNotNull('archived_by_event_id')->count());
    }

    /**
     * Simulate a pre-migration archive: event, form and field share the same
     * `deleted_at` and no marker exists yet.
     *
     * @return array{0: Event, 1: Form, 2: FormField}
     */
    private function legacyArchive(string $timestamp): array
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $field = FormField::factory()->create(['form_id' => $form->id]);

        DB::table('events')->where('id', $event->id)->update(['deleted_at' => $timestamp]);
        DB::table('forms')->where('id', $form->id)->update(['deleted_at' => $timestamp]);
        DB::table('form_fields')->where('id', $field->id)->update(['deleted_at' => $timestamp]);

        return [$event, $form, $field];
    }

    private function runBackfill(): void
    {
        /** @var \Illuminate\Database\Migrations\Migration $migration */
        $migration = require database_path('migrations/2026_11_11_000002_backfill_archived_by_event_id.php');

        $migration->up();
    }
}

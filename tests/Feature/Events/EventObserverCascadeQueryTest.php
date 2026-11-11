<?php

namespace Tests\Feature\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Event\EventCategoryCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cascade soft delete/restore event harus berbiaya query konstan, bukan O(jumlah field).
 */
class EventObserverCascadeQueryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Batas atas query per operasi penuh `$event->delete()`/`$event->restore()`:
     * baris event, update agregat kategori, dan dua statement cascade — bukan hanya cascade.
     * Nilai harus tetap sama untuk 1 maupun 500 field.
     */
    private const MAX_QUERIES_PER_OPERATION = 8;

    public function test_query_count_is_constant_regardless_of_field_volume(): void
    {
        $small = $this->createEventWithChildren(1, 1);
        $large = $this->createEventWithChildren(25, 20);

        // Buat agregat kategori deterministik sebelum pengukuran agar query counter sama.
        app(EventCategoryCounter::class)->rebuild();

        $smallDelete = $this->queryCount(fn () => $small->delete());
        $smallRestore = $this->queryCount(fn () => $small->restore());
        $largeDelete = $this->queryCount(fn () => $large->delete());
        $largeRestore = $this->queryCount(fn () => $large->restore());

        $this->assertSame($smallDelete, $largeDelete, "delete: small={$smallDelete}, large={$largeDelete}");
        $this->assertSame($smallRestore, $largeRestore, "restore: small={$smallRestore}, large={$largeRestore}");
        $this->assertLessThanOrEqual(self::MAX_QUERIES_PER_OPERATION, $largeDelete);
        $this->assertLessThanOrEqual(self::MAX_QUERIES_PER_OPERATION, $largeRestore);
    }

    public function test_cascade_soft_deletes_then_restores_every_child(): void
    {
        $event = $this->createEventWithChildren(25, 20);
        $otherEvent = $this->createEventWithChildren(1, 1);

        $formIds = DB::table('forms')->where('event_id', $event->id)->pluck('id')->all();
        $otherFormIds = DB::table('forms')->where('event_id', $otherEvent->id)->pluck('id')->all();
        $this->assertCount(25, $formIds);
        $this->assertCount(1, $otherFormIds);

        $event->delete();

        $this->assertSame(0, DB::table('forms')->where('event_id', $event->id)->whereNull('deleted_at')->count());
        $this->assertSame(25, DB::table('forms')->where('event_id', $event->id)->whereNotNull('deleted_at')->count());
        $this->assertSame(0, DB::table('form_fields')->whereIn('form_id', $formIds)->whereNull('deleted_at')->count());
        $this->assertSame(500, DB::table('form_fields')->whereIn('form_id', $formIds)->whereNotNull('deleted_at')->count());

        // Blast radius: event lain tidak boleh ikut ter-soft-delete.
        $this->assertSame(0, DB::table('forms')->where('event_id', $otherEvent->id)->whereNotNull('deleted_at')->count());
        $this->assertSame(0, DB::table('form_fields')->whereIn('form_id', $otherFormIds)->whereNotNull('deleted_at')->count());

        $event->restore();

        $this->assertSame(25, DB::table('forms')->where('event_id', $event->id)->whereNull('deleted_at')->count());
        $this->assertSame(0, DB::table('forms')->where('event_id', $event->id)->whereNotNull('deleted_at')->count());
        $this->assertSame(500, DB::table('form_fields')->whereIn('form_id', $formIds)->whereNull('deleted_at')->count());
        $this->assertSame(0, DB::table('form_fields')->whereIn('form_id', $formIds)->whereNotNull('deleted_at')->count());

        // Blast radius: restore event lain juga tidak boleh mengubah child event lain.
        $this->assertSame(0, DB::table('forms')->where('event_id', $otherEvent->id)->whereNotNull('deleted_at')->count());
        $this->assertSame(0, DB::table('form_fields')->whereIn('form_id', $otherFormIds)->whereNotNull('deleted_at')->count());
    }

    public function test_event_without_forms_cascades_within_query_bound(): void
    {
        $event = Event::factory()->create([
            'category' => 'etc',
            'session' => 'general',
            'status' => EventStatus::Draft,
        ]);

        app(EventCategoryCounter::class)->rebuild();

        $deleteQueries = $this->queryCount(fn () => $event->delete());
        $restoreQueries = $this->queryCount(fn () => $event->restore());

        $this->assertLessThanOrEqual(self::MAX_QUERIES_PER_OPERATION, $deleteQueries);
        $this->assertLessThanOrEqual(self::MAX_QUERIES_PER_OPERATION, $restoreQueries);
    }

    /**
     * Bulk UPDATE aman hanya karena Form/FormField tidak punya model listener; kalau
     * suatu saat listener ditambahkan, cascade ini akan melewatinya. Assertion ini
     * menjaga invariant tersebut lewat API dispatcher Laravel yang stabil.
     */
    public function test_form_and_form_field_have_no_model_listeners(): void
    {
        // `#[ObservedBy]` didaftarkan saat model boot (whenBooted); paksa boot agar
        // assertion tidak vacuous ketika test ini dijalankan sendiri lewat --filter.
        new Form();
        new FormField();

        $dispatcher = Form::getEventDispatcher();
        $events = [
            'creating', 'created', 'saving', 'saved', 'updating', 'updated',
            'deleting', 'deleted', 'restoring', 'restored', 'trashed',
            'forceDeleting', 'forceDeleted',
        ];

        foreach ([Form::class, FormField::class] as $model) {
            foreach ($events as $event) {
                $this->assertFalse(
                    $dispatcher->hasListeners("eloquent.{$event}: {$model}"),
                    "Bulk cascade melewatkan listener eloquent.{$event}: {$model}",
                );
            }
        }
    }

    /**
     * BE-P2-017: a child archived independently before the event keeps its own
     * archive timestamp and is not revived by the event restore.
     */
    public function test_cascade_leaves_independently_archived_children_untouched(): void
    {
        $event = $this->createEventWithChildren(2, 1);
        $archivedForm = Form::where('event_id', $event->id)->orderBy('id')->firstOrFail();
        $archivedField = FormField::where('form_id', $archivedForm->id)->firstOrFail();
        $activeForm = Form::where('event_id', $event->id)->where('id', '!=', $archivedForm->id)->firstOrFail();

        // Arsipkan form dan field terpisah sebelum event, lalu backdate deleted_at.
        $archivedForm->delete();
        DB::table('forms')->where('id', $archivedForm->id)->update(['deleted_at' => '2020-01-01 00:00:00']);
        DB::table('form_fields')->where('id', $archivedField->id)->update(['deleted_at' => '2020-01-01 00:00:00']);

        app(EventCategoryCounter::class)->rebuild();

        $event->delete();

        // Delete-side: arsip mandiri tidak di-restamp.
        $this->assertSame('2020-01-01 00:00:00', DB::table('forms')->where('id', $archivedForm->id)->value('deleted_at'));
        $this->assertSame('2020-01-01 00:00:00', DB::table('form_fields')->where('id', $archivedField->id)->value('deleted_at'));
        $this->assertNotNull(DB::table('forms')->where('id', $activeForm->id)->value('deleted_at'));

        $event->restore();

        // Restore-side: arsip mandiri tetap ter-soft-delete dengan waktu aslinya.
        $this->assertSame('2020-01-01 00:00:00', DB::table('forms')->where('id', $archivedForm->id)->value('deleted_at'));
        $this->assertSame('2020-01-01 00:00:00', DB::table('form_fields')->where('id', $archivedField->id)->value('deleted_at'));

        $this->assertNull(DB::table('forms')->where('id', $activeForm->id)->value('deleted_at'));
        $this->assertSame(0, DB::table('form_fields')->where('form_id', $activeForm->id)->whereNotNull('deleted_at')->count());
    }

    /**
     * Restore only revives the set the event archived, even when another
     * soft-deleted form of the same event exists (marker is not this event).
     */
    public function test_restore_skips_children_trashed_without_the_event_marker(): void
    {
        $event = $this->createEventWithChildren(2, 1);

        $event->delete();

        $forms = Form::withTrashed()->where('event_id', $event->id)->orderBy('id')->get();
        $this->assertCount(2, $forms);

        $independentlyArchived = $forms->first();
        DB::table('forms')->where('id', $independentlyArchived->id)->update([
            'deleted_at' => '2021-05-05 00:00:00',
            'archived_by_event_id' => null,
        ]);

        $event->restore();

        $this->assertSame('2021-05-05 00:00:00', DB::table('forms')->where('id', $independentlyArchived->id)->value('deleted_at'));

        $restored = Form::query()->where('event_id', $event->id)->pluck('id')->all();
        $this->assertCount(1, $restored);
        $this->assertNotSame((string) $independentlyArchived->id, (string) $restored[0]);
        $this->assertSame(0, DB::table('form_fields')->whereIn('form_id', $restored)->whereNotNull('deleted_at')->count());
    }

    /**
     * Buat event beserta form/field lewat bulk insert field agar setup 500 baris cepat.
     */
    private function createEventWithChildren(int $formCount, int $fieldsPerForm): Event
    {
        $event = Event::factory()->create([
            'category' => 'etc',
            'session' => 'general',
            'status' => EventStatus::Draft,
        ]);

        for ($formIndex = 0; $formIndex < $formCount; $formIndex++) {
            $form = Form::factory()->create(['event_id' => $event->id]);
            $this->insertFields($form, $fieldsPerForm);
        }

        return $event;
    }

    private function insertFields(Form $form, int $count): void
    {
        if ($count === 0) {
            return;
        }

        $now = now();
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'input_type' => 'input',
                'label' => 'Field '.$index,
                'description' => null,
                'name' => 'field_'.$index,
                'metadata' => json_encode(['rules' => []]),
                'form_id' => $form->id,
                'order' => $index + 1,
                'is_append' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('form_fields')->insert($rows);
    }

    /**
     * Jumlah statement SQL yang dieksekusi callback. State query log sebelumnya
     * dipulihkan: hanya mematikan log bila helper ini yang menyalakannya.
     */
    private function queryCount(\Closure $callback): int
    {
        $wasLogging = DB::connection()->logging();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::flushQueryLog();

        if (! $wasLogging) {
            DB::disableQueryLog();
        }

        return $count;
    }
}

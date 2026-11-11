<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BE-P2-017 (L-3): preserve the legacy restore behaviour for archives created
 * before `archived_by_event_id` existed.
 *
 * A pre-migration event archive restamped every child to the archive time, so a
 * row is considered cascade-archived when its `deleted_at` equals its event's
 * `deleted_at` (forms) or its form's `deleted_at` (fields). Only rows that still
 * have a NULL marker are touched, so the migration is idempotent and never
 * overwrites a marker written by the observer.
 *
 * Comparison uses the stored timestamp precision (seconds), matching what the
 * old bulk `UPDATE ... deleted_at = now()` wrote. Rows without a matching
 * timestamp were archived independently before the event and stay archived.
 *
 * `down()` is intentionally a no-op: this migration writes the same column the
 * observer writes for archives created after deploy, so the two writes cannot be
 * distinguished afterwards. Clearing the marker would break restore for those
 * newer archives; the only safe rollback is the forward-fix of keeping the
 * backfilled markers.
 */
return new class () extends Migration {
    private const EVENT_CHUNK = 200;

    public function up(): void
    {
        DB::table('events')
            ->whereNotNull('deleted_at')
            ->select(['id', 'deleted_at'])
            ->orderBy('id')
            ->chunkById(self::EVENT_CHUNK, function ($events): void {
                foreach ($events as $event) {
                    DB::table('forms')
                        ->where('event_id', $event->id)
                        ->whereNull('archived_by_event_id')
                        ->where('deleted_at', $event->deleted_at)
                        ->update(['archived_by_event_id' => $event->id]);

                    $this->markFieldsArchivedWithTheirForm((string) $event->id);
                }
            });
    }

    /**
     * Fields archived together with their form share the form's archive time.
     */
    private function markFieldsArchivedWithTheirForm(string $eventId): void
    {
        $formsByTimestamp = [];

        $forms = DB::table('forms')
            ->where('event_id', $eventId)
            ->where('archived_by_event_id', $eventId)
            ->get(['id', 'deleted_at']);

        foreach ($forms as $form) {
            $formsByTimestamp[(string) $form->deleted_at][] = $form->id;
        }

        foreach ($formsByTimestamp as $timestamp => $formIds) {
            DB::table('form_fields')
                ->whereIn('form_id', $formIds)
                ->whereNull('archived_by_event_id')
                ->where('deleted_at', $timestamp)
                ->update(['archived_by_event_id' => $eventId]);
        }
    }
};

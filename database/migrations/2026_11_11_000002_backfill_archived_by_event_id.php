<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** BE-P2-017 (L-3): preserve the legacy restore behaviour for archives created before `archived_by_event_id` existed. */
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

    /** Fields archived together with their form share the form's archive time. */
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

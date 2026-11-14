<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\Event\EventCategoryCounter;
use App\Services\Event\EventCategoryOptionService;
use App\Services\Event\EventSessionOptionService;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Event\EventCache;
use Illuminate\Support\Facades\DB;

/** Side effect event: cascade form/field, cache, dan agregat kategori. */
class EventObserver
{
    public function __construct(private readonly EventCategoryCounter $categoryCounter)
    {
    }

    /** Handle the Event "created" event. */
    public function created(Event $event): void
    {
        $this->rememberOptionTokens($event);
        $this->categoryCounter->applyDelta([], $this->liveCategoryTokens($event));
        $this->invalidateEventCache(true);
    }

    /** Handle the Event "updated" event. */
    public function updated(Event $event): void
    {
        // Registrasi hanya mengubah jumlah pendaftar: jangan buang statistik event (mahal).
        $onlyRegistrations = $event->wasChanged('registered_count')
            && ! $event->wasChanged([
                'title', 'slug', 'description', 'start_date', 'end_date',
                'registration_start', 'registration_end', 'location', 'quota',
                'banner', 'price', 'session', 'status', 'category',
            ]);

        $categoryOrSessionChanged = $event->wasChanged(['category', 'session']);

        if ($event->wasChanged(['category', 'deleted_at'])) {
            $this->categoryCounter->applyDelta(
                $this->previousCategoryTokens($event),
                $this->liveCategoryTokens($event),
            );
        }

        if ($categoryOrSessionChanged) {
            $this->rememberOptionTokens($event);
        }

        $this->invalidateEventCache($categoryOrSessionChanged, $onlyRegistrations);
    }

    /** Handle the Event "deleted" event. */
    public function deleted(Event $event): void
    {
        // Soft delete mengubah deleted_at lewat query builder (tanpa event `updated`), jadi
        // hitungan dilepas di sini. Force delete event yang sudah ter-soft-delete tidak
        // mengurangi lagi karena kontribusinya sudah lepas saat soft delete.
        if (! $event->isForceDeleting() || $event->getOriginal('deleted_at') === null) {
            $this->categoryCounter->applyDelta(Event::tokensFromCsv((string) $event->category), []);
        }

        $this->invalidateEventCache(true);
    }

    /** Cascade soft-delete to child forms (and their fields) so an event draft removal (wizard Cancel) does not leave orphaned forms behind. */
    public function deleting(Event $event): void
    {
        $archivedAt = now();

        DB::transaction(function () use ($event, $archivedAt): void {
            // Draft bukan form "hidup": baris draft sengaja tidak diarsipkan
            // bersama event sehingga pekerjaan yang belum dipublikasikan tidak
            // ikut terhapus. Form published tetap diarsipkan seperti sebelumnya.
            Form::query()
                ->where('event_id', $event->id)
                ->published()
                ->update([
                    'deleted_at' => $archivedAt,
                    'archived_by_event_id' => $event->id,
                ]);

            FormField::query()
                ->whereIn(
                    'form_id',
                    Form::withTrashed()
                        ->select('id')
                        ->where('event_id', $event->id)
                        ->where('archived_by_event_id', $event->id),
                )
                ->update([
                    'deleted_at' => $archivedAt,
                    'archived_by_event_id' => $event->id,
                ]);
        }, 3);
    }

    /** Restore only rows marked by this event; independent archives stay trashed. Restore forms before fields to preserve lock order and retry deadlocks. */
    public function restored(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            // Capture the cascade set before the marker is cleared; field restore
            // is scoped to those forms so a field of an independently archived
            // form is never revived on its own.
            $formIds = Form::onlyTrashed()
                ->where('event_id', $event->id)
                ->where('archived_by_event_id', $event->id)
                ->published()
                ->pluck('id');

            Form::onlyTrashed()
                ->where('event_id', $event->id)
                ->where('archived_by_event_id', $event->id)
                ->published()
                ->update(['deleted_at' => null, 'archived_by_event_id' => null]);

            if ($formIds->isEmpty()) {
                return;
            }

            FormField::onlyTrashed()
                ->whereIn('form_id', $formIds->all())
                ->where('archived_by_event_id', $event->id)
                ->update(['deleted_at' => null, 'archived_by_event_id' => null]);
        }, 3);

        $this->invalidateEventCache(true);
    }

    /** Handle the Event "force deleted" event. */
    public function forceDeleted(Event $event): void
    {
        $this->invalidateEventCache(true);
    }

    /**
     *
     * Token kategori event saat ini; kosong bila event soft-deleted (tidak ikut chart).
     *
     * @return list<string>
     *
     */
    private function liveCategoryTokens(Event $event): array
    {
        if ($event->trashed()) {
            return [];
        }

        return Event::tokensFromCsv((string) $event->category);
    }

    /**
     *
     * Token kategori sebelum update; kosong bila sebelumnya sudah soft-deleted.
     *
     * @return list<string>
     *
     */
    private function previousCategoryTokens(Event $event): array
    {
        if ($event->getOriginal('deleted_at') !== null) {
            return [];
        }

        return Event::tokensFromCsv((string) $event->getOriginal('category'));
    }

    /** Simpan token kategori/sesi event ini agar opsi filter tidak perlu DISTINCT 1 juta baris. */
    private function rememberOptionTokens(Event $event): void
    {
        EventCache::mergeOptionTokens(EventCategoryOptionService::RAW_CACHE_KEY, $event->category);
        EventCache::mergeOptionTokens(EventSessionOptionService::RAW_CACHE_KEY, $event->session);
    }

    /** Invalidasi per kebutuhan: daftar & statistik selalu, kategori hanya bila berubah. */
    private function invalidateEventCache(bool $categoryChanged, bool $registrationsOnly = false): void
    {
        EventCache::bump(EventCache::BUSTER_LIST);

        if ($registrationsOnly) {
            EventCache::bump(EventCache::BUSTER_REGISTRANTS);

            return;
        }

        EventCache::bump(EventCache::BUSTER_STATS);
        EventCache::bump(EventCache::BUSTER_REGISTRANTS);

        if ($categoryChanged) {
            EventCache::bump(EventCache::BUSTER_CATEGORY);
        }
    }
}

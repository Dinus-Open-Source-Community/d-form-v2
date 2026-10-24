<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\Event\EventCategoryCounter;
use App\Services\Event\EventCategoryOptionService;
use App\Services\Event\EventSessionOptionService;
use App\Models\Form;
use App\Services\Event\EventCache;

class EventObserver
{
    public function __construct(private readonly EventCategoryCounter $categoryCounter)
    {
    }

    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        $this->rememberOptionTokens($event);
        $this->categoryCounter->applyDelta([], $this->liveCategoryTokens($event));
        $this->invalidateEventCache(true);
    }

    /**
     * Handle the Event "updated" event.
     */
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

    /**
     * Handle the Event "deleted" event.
     */
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

    /**
     * Cascade soft-delete to child forms (and their fields) so an event draft
     * removal (wizard Cancel) does not leave orphaned forms behind.
     */
    public function deleting(Event $event): void
    {
        $event->forms()->withTrashed()->get()->each(function (Form $form): void {
            $form->formFields()->withTrashed()->get()->each(function ($field): void {
                $field->delete();
            });
            $form->delete();
        });
    }

    /**
     * Handle the Event "restored" event.
     *
     * Hitungan kategori tidak disentuh di sini: `restore()` memicu `updated` dengan
     * deleted_at berubah, dan delta di `updated` sudah menambahkannya kembali.
     */
    public function restored(Event $event): void
    {
        $event->forms()->withTrashed()->get()->each(function (Form $form): void {
            $form->formFields()->withTrashed()->get()->each(function ($field): void {
                $field->restore();
            });
            $form->restore();
        });

        $this->invalidateEventCache(true);
    }

    /**
     * Handle the Event "force deleted" event.
     */
    public function forceDeleted(Event $event): void
    {
        $this->invalidateEventCache(true);
    }

    /**
     * Token kategori event saat ini; kosong bila event soft-deleted (tidak ikut chart).
     *
     * @return list<string>
     */
    private function liveCategoryTokens(Event $event): array
    {
        if ($event->trashed()) {
            return [];
        }

        return Event::tokensFromCsv((string) $event->category);
    }

    /**
     * Token kategori sebelum update; kosong bila sebelumnya sudah soft-deleted.
     *
     * @return list<string>
     */
    private function previousCategoryTokens(Event $event): array
    {
        if ($event->getOriginal('deleted_at') !== null) {
            return [];
        }

        return Event::tokensFromCsv((string) $event->getOriginal('category'));
    }

    /**
     * Simpan token kategori/sesi event ini agar opsi filter tidak perlu DISTINCT 1 juta baris.
     */
    private function rememberOptionTokens(Event $event): void
    {
        EventCache::mergeOptionTokens(EventCategoryOptionService::RAW_CACHE_KEY, $event->category);
        EventCache::mergeOptionTokens(EventSessionOptionService::RAW_CACHE_KEY, $event->session);
    }

    /**
     * Invalidasi per kebutuhan: daftar & statistik selalu, kategori hanya bila berubah.
     */
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

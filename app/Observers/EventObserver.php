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

/**
 * Side effect event: cascade form/field, cache, dan agregat kategori.
 *
 * Cascade delete/restore memakai `DB::transaction($callback, 3)`. Retry Laravel mencakup
 * deadlock 1213 dan lock-wait timeout 1205 — `ConcurrencyErrorDetector` mencocokkan pesan
 * "Lock wait timeout exceeded; try restarting transaction". Retry bersifat inert bila
 * observer berjalan di dalam transaksi milik pemanggil (`transactions > 1`): Laravel
 * melempar `DeadlockException`, bukan mengulang. Saat ini tidak ada pemanggil yang
 * membungkus delete/restore event dalam transaksi luar.
 */
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
     *
     * Bulk `UPDATE` (bukan delete per model) menjaga jumlah query konstan. Form dan
     * FormField tidak punya observer/listener, jadi tidak ada side effect model yang
     * terlewat; `updated_at` diisi otomatis oleh Eloquent Builder::update().
     *
     * Form di-update lebih dulu, baru field: subquery field memakai `withTrashed()`
     * sehingga form yang baru saja di-soft-delete tetap cocok. Urutan ini menghindari
     * lock S->X pada `forms` dan ABBA dengan `FormController::update` (forms lalu fields);
     * `DB::transaction(..., 3)` menangani deadlock 1213 yang tersisa.
     */
    public function deleting(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            Form::withTrashed()
                ->where('event_id', $event->id)
                ->update(['deleted_at' => now()]);

            FormField::withTrashed()
                ->whereIn('form_id', Form::withTrashed()->select('id')->where('event_id', $event->id))
                ->update(['deleted_at' => now()]);
        }, 3);
    }

    /**
     * Handle the Event "restored" event.
     *
     * Hitungan kategori tidak disentuh di sini: `restore()` memicu `updated` dengan
     * deleted_at berubah, dan delta di `updated` sudah menambahkannya kembali.
     *
     * `onlyTrashed()` meniru perilaku `restore()` per model: baris yang masih aktif
     * adalah no-op, sehingga form yang diarsipkan terpisah tetap ikut dikembalikan.
     * Urutan form-lalu-field dan retry deadlock sama dengan {@see deleting()}.
     */
    public function restored(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            Form::onlyTrashed()
                ->where('event_id', $event->id)
                ->update(['deleted_at' => null]);

            FormField::onlyTrashed()
                ->whereIn('form_id', Form::withTrashed()->select('id')->where('event_id', $event->id))
                ->update(['deleted_at' => null]);
        }, 3);

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

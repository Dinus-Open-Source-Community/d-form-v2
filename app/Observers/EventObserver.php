<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\Form;
use App\Services\Event\EventCache;

class EventObserver
{
    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        $this->invalidateEventCache(true);
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        $this->invalidateEventCache($event->wasChanged(['category', 'session']));
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
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
     * Invalidasi per kebutuhan: daftar & statistik selalu, kategori hanya bila berubah.
     */
    private function invalidateEventCache(bool $categoryChanged): void
    {
        EventCache::bump(EventCache::BUSTER_LIST);
        EventCache::bump(EventCache::BUSTER_STATS);

        if ($categoryChanged) {
            EventCache::bump(EventCache::BUSTER_CATEGORY);
        }
    }
}

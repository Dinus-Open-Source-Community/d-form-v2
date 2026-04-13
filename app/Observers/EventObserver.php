<?php

namespace App\Observers;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;

class EventObserver
{
    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        $this->invalidateEventListCache();
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        $this->invalidateEventListCache();
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
        $this->invalidateEventListCache();
    }

    /**
     * Handle the Event "restored" event.
     */
    public function restored(Event $event): void
    {
        $this->invalidateEventListCache();
    }

    /**
     * Handle the Event "force deleted" event.
     */
    public function forceDeleted(Event $event): void
    {
        //
    }

    private function invalidateEventListCache(): void
    {
        try {
            Cache::tags(['events'])->flush();

            return;
        } catch (\BadMethodCallException|\RuntimeException) {
            //
        }

        Cache::forever('events:list:cache:buster', (int) Cache::get('events:list:cache:buster', 0) + 1);
    }
}

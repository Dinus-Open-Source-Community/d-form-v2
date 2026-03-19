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
        Cache::tags(['events'])->flush();
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        Cache::tags(['events'])->flush();
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
        Cache::tags(['events'])->flush();
    }

    /**
     * Handle the Event "restored" event.
     */
    public function restored(Event $event): void
    {
        Cache::tags(['events'])->flush();
    }

    /**
     * Handle the Event "force deleted" event.
     */
    public function forceDeleted(Event $event): void
    {
        //
    }
}

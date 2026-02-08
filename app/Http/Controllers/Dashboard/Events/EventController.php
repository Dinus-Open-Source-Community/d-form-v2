<?php

namespace App\Http\Controllers\Dashboard\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('pages.dashboard.events.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.dashboard.events.create');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $event = Event::query()->where('id', $id);

        // if (auth()->guard()->user())
        $event = $event->withTrashed()->get()->first();
        //}

        return view('pages.dashboard.events.view', compact('event'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        return view('pages.dashboard.events.edit', compact('event'));
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EventsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function index(Request $request)
    {
        return inertia('Event');
    }

    public function show(string $id)
    {
        return inertia('EventDetail', ['eventId' => $id]);
    }
}

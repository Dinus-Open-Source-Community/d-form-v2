<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\Events\EventController;

// Routes for Dashboard
Route::name('dashboard.')->prefix('/dashboard')->middleware('auth')->group(function () {
    Route::get('/', function () {
        return view('pages.dashboard.home');
    })->name('home');

    Route::resource('/events', EventController::class)->only(['index', 'create', 'show', 'edit']);
    // Route::resource('/events/{event}/forms', EventFormController::class)->only(['index', 'create', 'show', 'edit'])
    // ->names('events.forms');
});
// End of Routes for Dashboard

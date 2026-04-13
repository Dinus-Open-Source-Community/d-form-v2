<?php

use App\Http\Controllers\Dashboard\Events\EventController;
use Illuminate\Support\Facades\Route;

Route::name('dashboard.')->prefix('/dashboard')->middleware('auth')->group(function () {
    Route::post('/events/{event}/restore', [EventController::class, 'restore'])
        ->name('events.restore');

    Route::resource('/events', EventController::class)->only([
        'index',
        'create',
        'store',
        'show',
        'edit',
        'update',
        'destroy',
    ]);
});

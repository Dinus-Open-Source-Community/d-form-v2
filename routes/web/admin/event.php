<?php

use App\Http\Controllers\Dashboard\Events\EventController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\Events\Forms\FieldOperationController;
use App\Http\Controllers\Dashboard\Events\Forms\FormController as EventFormController;
use App\Http\Controllers\Dashboard\Events\Forms\FormSubmissionController;

Route::name('dashboard.')->prefix('/dashboard')->middleware('auth')->group(function () {
    Route::get('/events/{event}/registration-status', [EventController::class, 'registrationStatus'])
        ->name('events.registration-status');

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

    Route::resource('/events/{event}/forms', EventFormController::class)->only(['index', 'create', 'show', 'edit'])
    ->names('events.forms');

    Route::post('/forms/{form}/fields', FieldOperationController::class)
        ->name('forms.fields');

    Route::post('/events/{event}/forms/{form}/submit', FormSubmissionController::class)->name('forms.submission');
});

// Route::name('dashboard.')->prefix('/dashboard')->middleware('auth')->group(function () {
//     Route::get('/events/{event}/registration-status', [EventController::class, 'registrationStatus'])
//         ->name('events.registration-status');

//     Route::post('/events/{event}/restore', [EventController::class, 'restore'])
//         ->name('events.restore');

//     Route::resource('/events', EventController::class)->only([
//         'index',
//         'create',
//         'store',
//         'show',
//         'edit',
//         'update',
//         'destroy',
//     ]);
// });

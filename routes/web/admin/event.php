<?php

use App\Http\Controllers\Dashboard\Events\EventController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\Events\Forms\FieldOperationController;
use App\Http\Controllers\Dashboard\Events\Forms\FormController as EventFormController;
use App\Http\Controllers\Dashboard\Events\Forms\FormFillController;
use App\Http\Controllers\Dashboard\Events\Forms\FormSubmissionController;
use App\Http\Controllers\Dashboard\Events\Forms\FormSubmissionsController;

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

    Route::resource('/events/{event}/forms', EventFormController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->names([
            'index' => 'events.forms.index',
            'create' => 'events.forms.create',
            'store' => 'events.forms.store',
            'show' => 'events.forms.show',
            'edit' => 'events.forms.edit',
            'update' => 'events.forms.update',
            'destroy' => 'events.forms.destroy',
        ]);

    Route::post('/events/{event}/forms/{form}/fields', [FieldOperationController::class, '__invoke'])
        ->name('events.forms.fields');

    Route::get('/events/{event}/forms/{form}/fill', FormFillController::class)
        ->name('events.forms.fill');

    Route::post('/events/{event}/forms/{form}/submit', FormSubmissionController::class)
        ->name('forms.submission');

    Route::get('/events/{event}/forms/{form}/submissions', FormSubmissionsController::class)
        ->name('events.forms.submissions');
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

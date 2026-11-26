<?php

use App\Http\Controllers\Dashboard\Du\DuCourseListController;
use App\Http\Controllers\Dashboard\Du\DuCourseSyncController;
use Illuminate\Support\Facades\Route;

Route::name('dashboard.du.')->prefix('/admin/du')->middleware(['auth', 'organizer'])->group(function (): void {
    Route::get('/courses', DuCourseListController::class)
        ->name('courses.index');

    Route::post('/courses/sync', DuCourseSyncController::class)
        ->middleware('throttle:du-sync')
        ->name('courses.sync');
});

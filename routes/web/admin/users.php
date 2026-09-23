<?php

use App\Http\Controllers\Dashboard\Users\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::name('dashboard.')->prefix('/admin')->middleware(['auth', 'organizer'])->group(function () {
    Route::resource('/users', UserManagementController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
});

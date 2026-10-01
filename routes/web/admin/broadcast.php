<?php

use App\Http\Controllers\Dashboard\Broadcasts\BroadcastController;
use Illuminate\Support\Facades\Route;

// Broadcast hub: snapshot-only. Sengaja hanya create/store/show —
// tanpa index/edit/update/destroy dan tanpa endpoint datasets reusable.
Route::middleware('auth')->prefix('/admin/broadcasts')->name('dashboard.broadcasts.')->group(function (): void {
    Route::get('/create', [BroadcastController::class, 'create'])->name('create');
    Route::post('/', [BroadcastController::class, 'store'])->name('store');
    Route::get('/{broadcast}', [BroadcastController::class, 'show'])->name('show');
});

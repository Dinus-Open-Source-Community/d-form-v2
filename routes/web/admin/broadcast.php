<?php

use App\Http\Controllers\Dashboard\Broadcasts\BroadcastController;
use Illuminate\Support\Facades\Route;

// Broadcast hub: index per-period untuk tab; konten boleh diubah saat draft/scheduled;
// snapshot-only untuk penerima (tanpa destroy dan tanpa endpoint datasets reusable).
Route::middleware('auth')->prefix('/admin/broadcasts')->name('dashboard.broadcasts.')->group(function (): void {
    Route::get('/create', [BroadcastController::class, 'create'])->name('create');
    Route::get('/', [BroadcastController::class, 'index'])->name('index');
    Route::post('/', [BroadcastController::class, 'store'])->name('store');
    Route::match(['put', 'patch'], '/{broadcast}', [BroadcastController::class, 'update'])->name('update');
    Route::post('/{broadcast}/send', [BroadcastController::class, 'send'])->name('send');
    Route::get('/{broadcast}', [BroadcastController::class, 'show'])->name('show');
});

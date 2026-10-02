<?php

use App\Http\Controllers\Dashboard\Broadcasts\BroadcastController;
use Illuminate\Support\Facades\Route;

// Broadcast hub: index per-period untuk tab; konten boleh diubah saat draft/scheduled;
// snapshot-only untuk penerima (tanpa destroy dan tanpa endpoint datasets reusable).
// Fitur broadcast dinonaktifkan (config/features.php, default mati).
// File ini sengaja dipertahankan tanpa dihapus; bila flag mati, route di bawah
// tidak didaftarkan sehingga seluruh URL /admin/broadcasts/* 404.
// Aktifkan lagi via FEATURE_BROADCAST=true.
if (! config('features.broadcast', false)) {
    return;
}

Route::middleware('auth')->prefix('/admin/broadcasts')->name('dashboard.broadcasts.')->group(function (): void {
    Route::get('/create', [BroadcastController::class, 'create'])->name('create');
    Route::get('/', [BroadcastController::class, 'index'])->name('index');
    Route::post('/', [BroadcastController::class, 'store'])->name('store');
    Route::match(['put', 'patch'], '/{broadcast}', [BroadcastController::class, 'update'])->name('update');
    Route::post('/{broadcast}/send', [BroadcastController::class, 'send'])->name('send');
    Route::get('/{broadcast}/preview', [BroadcastController::class, 'preview'])->name('preview');
    Route::post('/{broadcast}/test', [BroadcastController::class, 'testSend'])->name('test');
    Route::post('/{broadcast}/attachments', [BroadcastController::class, 'storeAttachment'])->name('attachments.store');
    Route::delete('/{broadcast}/attachments/{attachment}', [BroadcastController::class, 'destroyAttachment'])->name('attachments.destroy');
    Route::post('/{broadcast}/retry', [BroadcastController::class, 'retry'])->name('retry');
    Route::post('/{broadcast}/cancel', [BroadcastController::class, 'cancel'])->name('cancel');
    Route::get('/{broadcast}/tracking', [BroadcastController::class, 'tracking'])->name('tracking');
    Route::get('/{broadcast}', [BroadcastController::class, 'show'])->name('show');
});

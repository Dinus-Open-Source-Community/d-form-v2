<?php

use App\Http\Controllers\Dashboard\Broadcasting\BroadcastAttachmentController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastCancelController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastContentController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastDatasetController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastPreviewController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastRecipientController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastRetryController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastScheduleController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastSnapshotController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastTestController;
use Illuminate\Support\Facades\Route;

/**
 * Email Broadcasting — modul terpisah (PRD v1.0).
 * Seluruh route terisolasi di prefix /admin/broadcasts.
 *
 * Fitur broadcast dinonaktifkan (config/features.php, default mati).
 * File ini dipertahankan; bila flag mati, route tidak didaftarkan (404).
 * Aktifkan lagi via FEATURE_BROADCAST=true.
 */
if (! config('features.broadcast', false)) {
    return;
}

Route::middleware(['auth', 'broadcast.access'])->prefix('/admin/broadcasts')->name('dashboard.broadcasts.')->group(function (): void {
    Route::get('/', [BroadcastController::class, 'index'])->name('index');
    Route::get('/create', [BroadcastController::class, 'create'])->name('create');
    Route::post('/', [BroadcastController::class, 'store'])->name('store');

    Route::get('/datasets', [BroadcastDatasetController::class, 'index'])->name('datasets.index');
    Route::post('/datasets', [BroadcastDatasetController::class, 'store'])->name('datasets.store');
    Route::get('/datasets/preview', [BroadcastDatasetController::class, 'preview'])->name('datasets.preview');

    Route::get('/{broadcast}', [BroadcastController::class, 'show'])->name('show');
    Route::delete('/{broadcast}', [BroadcastController::class, 'destroy'])->name('destroy');

    Route::post('/{broadcast}/content', BroadcastContentController::class)->name('content.save');
    Route::post('/{broadcast}/snapshot', BroadcastSnapshotController::class)->name('snapshot.generate');

    Route::get('/{broadcast}/recipients', [BroadcastRecipientController::class, 'index'])->name('recipients.index');
    Route::post('/{broadcast}/recipients', [BroadcastRecipientController::class, 'store'])->name('recipients.store');
    Route::patch('/{broadcast}/recipients/{recipient}', [BroadcastRecipientController::class, 'update'])->name('recipients.update');
    Route::delete('/{broadcast}/recipients/{recipient}', [BroadcastRecipientController::class, 'destroy'])->name('recipients.destroy');

    Route::post('/{broadcast}/attachments', [BroadcastAttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/{broadcast}/attachments/{attachment}', [BroadcastAttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('/{broadcast}/preview', BroadcastPreviewController::class)->name('preview');
    Route::post('/{broadcast}/test', BroadcastTestController::class)->name('test');

    Route::post('/{broadcast}/schedule', [BroadcastScheduleController::class, 'schedule'])->name('schedule');
    Route::patch('/{broadcast}/schedule', [BroadcastScheduleController::class, 'update'])->name('schedule.update');

    Route::post('/{broadcast}/cancel', BroadcastCancelController::class)->name('cancel');
    Route::post('/{broadcast}/retry-failed', BroadcastRetryController::class)->name('retry');
});

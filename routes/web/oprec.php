<?php

use App\Http\Controllers\Recruitment\AttendanceController;
use App\Http\Controllers\Recruitment\ApplicationController;
use App\Http\Controllers\Recruitment\CorrectionRequestController;
use App\Http\Controllers\Recruitment\FeedbackController;
use App\Http\Controllers\Recruitment\LandingController;
use App\Http\Controllers\Recruitment\TrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('open-recruitment')
    ->name('open-recruitment.')
    ->group(function (): void {
        Route::get('/', LandingController::class)->name('landing');

        Route::get('/apply', [ApplicationController::class, 'create'])->name('apply');
        Route::post('/apply', [ApplicationController::class, 'store'])
            ->middleware(['recruitment.period.open', 'throttle:oprec-apply'])
            ->name('apply.store');

        Route::get('/success', [ApplicationController::class, 'success'])->name('success');

        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');

        Route::get('/track', [TrackingController::class, 'login'])->name('track.login');
        Route::post('/track', [TrackingController::class, 'authenticate'])
            ->middleware('throttle:oprec-track')
            ->name('track.authenticate');

        Route::middleware('recruitment.tracking.session')->group(function (): void {
            Route::get('/track/dashboard', [TrackingController::class, 'show'])->name('track.show');
            Route::get('/track/edit', [TrackingController::class, 'edit'])->name('track.edit');
            Route::put('/track', [TrackingController::class, 'update'])->name('track.update');
            Route::post('/track/correction', [CorrectionRequestController::class, 'store'])->name('track.correction');
            Route::get('/track/feedback', [FeedbackController::class, 'create'])->name('track.feedback');
            Route::post('/track/feedback', [FeedbackController::class, 'store'])->name('track.feedback.store');
            Route::post('/track/logout', [TrackingController::class, 'logout'])->name('track.logout');
        });
    });

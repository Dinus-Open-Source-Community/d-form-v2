<?php

use App\Http\Controllers\OpenRecruitmentController;
use Illuminate\Support\Facades\Route;

// Open Recruitment — halaman publik (applicant, tanpa akun).
Route::get('/open-recruitment', [OpenRecruitmentController::class, 'index'])
    ->name('open-recruitment.index');

Route::get('/open-recruitment/apply', [OpenRecruitmentController::class, 'apply'])
    ->name('open-recruitment.apply');

// POST submit diproses client-side (store dummy); route ini placeholder
// agar nama route tersedia dan siap diisi controller asli.
Route::post('/open-recruitment/apply', fn () => redirect()->route('open-recruitment.index'))
    ->name('open-recruitment.store');

Route::get('/open-recruitment/submitted/{registrationNumber}', [OpenRecruitmentController::class, 'submitted'])
    ->name('open-recruitment.submitted');

Route::get('/open-recruitment/tracking', [OpenRecruitmentController::class, 'tracking'])
    ->name('open-recruitment.tracking');

Route::get('/open-recruitment/tracking/{registrationNumber}', [OpenRecruitmentController::class, 'trackingShow'])
    ->name('open-recruitment.tracking.show');

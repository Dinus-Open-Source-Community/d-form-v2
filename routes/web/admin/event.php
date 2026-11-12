<?php

use App\Http\Controllers\Dashboard\Events\EventController;
use App\Http\Controllers\Dashboard\Events\EventLaporanController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\Events\Forms\FieldOperationController;
use App\Http\Controllers\Dashboard\Events\Forms\FormController as EventFormController;
use App\Http\Controllers\Dashboard\Events\Forms\FormImageUploadController;
use App\Http\Controllers\Dashboard\Events\Forms\FormAnswerFileController;
use App\Http\Controllers\Dashboard\Events\Forms\FormAnswerReviewController;
use App\Http\Controllers\Dashboard\Events\Forms\BulkFormAnswerReviewController;
use App\Http\Controllers\Dashboard\Events\Forms\FormFillController;
use App\Http\Controllers\Dashboard\Events\Forms\FormSubmissionController;
use App\Http\Controllers\Dashboard\Events\Forms\FormSubmitSuccessController;
use App\Http\Controllers\Dashboard\Events\Forms\FormSubmissionsController;
use App\Http\Controllers\Dashboard\Events\Forms\FormShareBarcodeController;
use App\Http\Controllers\Dashboard\Events\Forms\FormShareLinkController;
use App\Http\Controllers\Dashboard\Events\Forms\FormShareRedirectController;
use App\Http\Controllers\Dashboard\User\UserValidationController;

// Authorized access to registration uploads. Kept outside the auth-only groups
// so email links with a valid temporary signature still resolve for guest
// recipients; the controller rejects unsigned guests with 403.
Route::get('/form-answers/{formAnswer}/files/{field}/{filename}', FormAnswerFileController::class)
    ->where('field', '[A-Za-z0-9_.-]+')
    ->where('filename', '[^/]+')
    ->middleware('throttle:form-answer-file')
    ->name('dashboard.form-answers.files');

Route::get('/s/{slug}', FormShareRedirectController::class)
    ->where('slug', '[a-z0-9-]{3,50}')
    ->name('form-share.redirect');

Route::name('dashboard.')->prefix('/joined')->middleware('auth')->group(function () {
    Route::get('/{event}/forms/{form}/fill', FormFillController::class)
        ->name('events.forms.fill');

    Route::post('/{event}/forms/{form}/submit', FormSubmissionController::class)
        ->middleware('throttle:form-submit')
        ->name('forms.submission');

    Route::get('/{event}/forms/{form}/submitted', FormSubmitSuccessController::class)
        ->name('events.forms.submitted');

    Route::get('/users/check-email', [UserValidationController::class, 'checkEmail'])
        ->middleware('throttle:auth-email-check')
        ->name('users.check-email');
});

Route::name('dashboard.')->prefix('/admin')->middleware(['auth', 'organizer'])->group(function () {
    Route::get('/events/{event}/registration-status', [EventController::class, 'registrationStatus'])
        ->name('events.registration-status');

    Route::post('/events/{event}/restore', [EventController::class, 'restore'])
        ->name('events.restore');

    Route::get('/events/{event}/laporan', EventLaporanController::class)
        ->name('events.laporan');

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
        ->only(['create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->names([
            'create' => 'events.forms.create',
            'store' => 'events.forms.store',
            'show' => 'events.forms.show',
            'edit' => 'events.forms.edit',
            'update' => 'events.forms.update',
            'destroy' => 'events.forms.destroy',
        ]);

    Route::put('/events/{event}/forms/{form}/share-link', FormShareLinkController::class)
        ->name('events.forms.share-link');

    Route::get('/events/{event}/forms/{form}/share-link/barcode', FormShareBarcodeController::class)
        ->name('events.forms.share-link.barcode');

    Route::post('/events/{event}/forms/draft', [EventFormController::class, 'storeDraft'])
        ->name('events.forms.draft');

    // Halaman daftar form sudah dihapus (draft tampil di halaman detail event).
    // Tanpa route ini, GET pada URI yang sama dijawab 405 (karena `store` POST),
    // padahal URL lama harus 404.
    Route::get('/events/{event}/forms', static fn () => abort(404));

    Route::post('/events/{event}/forms/{form}/fields', [FieldOperationController::class, '__invoke'])
        ->name('events.forms.fields');

    Route::post('/events/{event}/forms/{form}/images', FormImageUploadController::class)
        ->name('events.forms.images');

    Route::get('/events/{event}/forms/{form}/submissions', FormSubmissionsController::class)
        ->name('events.forms.submissions');

    Route::patch('/events/{event}/forms/{form}/submissions/bulk-review', BulkFormAnswerReviewController::class)
        ->middleware('throttle:form-review-bulk')
        ->name('events.forms.submissions.bulk-review');

    Route::patch('/events/{event}/forms/{form}/submissions/{formAnswer}/review', FormAnswerReviewController::class)
        ->name('events.forms.submissions.review');
});

<?php

use App\Http\Controllers\Dashboard\Events\ArchivedEventController;
use App\Http\Controllers\Dashboard\Events\EventController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\Events\Forms\FieldOperationController;
use App\Http\Controllers\Dashboard\Events\Forms\FormController as EventFormController;
use App\Http\Controllers\Dashboard\Events\Forms\FormImageUploadController;
use App\Http\Controllers\Dashboard\Events\Forms\FormAnswerFileController;
use App\Http\Controllers\Dashboard\Events\Forms\FormAnswerReviewController;
use App\Http\Controllers\Dashboard\Events\Forms\DuRedeemIssuanceController;
use App\Http\Controllers\Dashboard\Events\Forms\DuFieldMappingController;
use App\Http\Controllers\Dashboard\Events\Forms\FormReferralController;
use App\Http\Controllers\Dashboard\Events\Forms\FormReferralCheckController;
use App\Http\Controllers\Dashboard\Events\Forms\FormAnswersCsvExportController;
use App\Http\Controllers\Dashboard\Events\Forms\FormAnswersZipExportController;
use App\Http\Controllers\Dashboard\Events\Forms\BulkFormAnswerReviewController;
use App\Http\Controllers\Dashboard\Events\Forms\FormFillController;
use App\Http\Controllers\Dashboard\Events\Forms\FormSubmissionController;
use App\Http\Controllers\Dashboard\Events\Forms\FormSubmitSuccessController;
use App\Http\Controllers\Dashboard\Events\Forms\FormSubmissionsController;
use App\Http\Controllers\Dashboard\Events\Forms\FormShareBarcodeController;
use App\Http\Controllers\Dashboard\Events\Forms\FormShareLinkController;
use App\Http\Controllers\Dashboard\Events\Forms\FormShareRedirectController;
use App\Http\Controllers\Dashboard\Events\Forms\FormSessionController;
use App\Http\Controllers\Dashboard\User\UserValidationController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('form-referral-write', static function (Request $request): Limit {
    return Limit::perMinute((int) config('events.forms.referral.write_rate_limit_per_minute', 30))
        ->by($request->user()?->id ?? $request->ip());
});

RateLimiter::for('form-referral-check', static function (Request $request): Limit {
    return Limit::perMinute((int) config('events.forms.referral.check_rate_limit_per_minute', 60))
        ->by($request->user()?->id ?? $request->ip());
});

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

Route::name('dashboard.')->prefix('/joined')->middleware(['form.participant', 'event.not-archived'])->group(function () {
    Route::get('/{event}/forms/{form}/fill', FormFillController::class)
        ->name('events.forms.fill');

    Route::post('/{event}/forms/{form}/submit', FormSubmissionController::class)
        ->middleware('throttle:form-submit')
        ->name('forms.submission');

    Route::post('/{event}/forms/{form}/referral-code/check', FormReferralCheckController::class)
        ->middleware('throttle:form-referral-check')
        ->name('forms.referral-check');

    Route::get('/{event}/forms/{form}/submitted', FormSubmitSuccessController::class)
        ->name('events.forms.submitted');

});

Route::name('dashboard.')->prefix('/joined')->middleware('auth')->group(function () {
    Route::get('/users/check-email', [UserValidationController::class, 'checkEmail'])
        ->middleware('throttle:auth-email-check')
        ->name('users.check-email');
});

Route::name('dashboard.')->prefix('/admin')->middleware(['auth', 'organizer', 'event.not-archived'])->group(function () {
    Route::get('/events/{event}/registration-status', [EventController::class, 'registrationStatus'])
        ->name('events.registration-status');

    Route::post('/events/{event}/restore', [EventController::class, 'restore'])
        ->name('events.restore');

    Route::delete('/events/{event}/force', [ArchivedEventController::class, 'destroy'])
        ->middleware('throttle:event-force-delete')
        ->name('events.force-delete');

    Route::patch('/events/{event}/status', [EventController::class, 'updateStatus'])
        ->name('events.status');

    // Didaftarkan sebelum `Route::resource` agar `/events/archived` tidak tertangkap `/events/{event}`.
    Route::get('/events/archived', [ArchivedEventController::class, 'index'])
        ->name('events.archived');

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

    Route::put('/events/{event}/forms/{form}/du-field-map', DuFieldMappingController::class)
        ->middleware('throttle:du-mapping')
        ->name('events.forms.du-field-map');

    Route::get('/events/{event}/forms/{form}/share-link/barcode', FormShareBarcodeController::class)
        ->name('events.forms.share-link.barcode');

    Route::post('/events/{event}/forms/draft', [EventFormController::class, 'storeDraft'])
        ->middleware('throttle:du-sync')
        ->name('events.forms.draft');

    // Halaman daftar form sudah dihapus (draft tampil di halaman detail event).
    // Tanpa route ini, GET pada URI yang sama dijawab 405 (karena `store` POST),
    // padahal URL lama harus 404.
    Route::get('/events/{event}/forms', static fn () => abort(404));

    Route::post('/events/{event}/forms/{form}/sessions', [FormSessionController::class, 'store'])
        ->name('events.forms.sessions.store');

    Route::delete('/events/{event}/forms/{form}/sessions/{session}', [FormSessionController::class, 'destroy'])
        ->name('events.forms.sessions.destroy');

    Route::post('/events/{event}/forms/{form}/fields', [FieldOperationController::class, '__invoke'])
        ->name('events.forms.fields');

    Route::post('/events/{event}/forms/{form}/images', FormImageUploadController::class)
        ->name('events.forms.images');

    Route::get('/events/{event}/forms/{form}/submissions', FormSubmissionsController::class)
        ->name('events.forms.submissions');

    Route::get('/events/{event}/forms/{form}/answers.csv', FormAnswersCsvExportController::class)
        ->middleware('throttle:form-answers-export')
        ->name('events.forms.answers-csv');

    Route::get('/events/{event}/forms/{form}/answers.zip', FormAnswersZipExportController::class)
        ->middleware('throttle:form-answers-export')
        ->name('events.forms.answers-zip');

    Route::patch('/events/{event}/forms/{form}/submissions/bulk-review', BulkFormAnswerReviewController::class)
        ->middleware('throttle:form-review-bulk')
        ->name('events.forms.submissions.bulk-review');

    Route::post('/events/{event}/forms/{form}/submissions/{formAnswer}/du-issuance', DuRedeemIssuanceController::class)
        ->middleware('throttle:du-issuance')
        ->name('events.forms.submissions.du-issuance');

    Route::patch('/events/{event}/forms/{form}/referral', [FormReferralController::class, 'updateSettings'])
        ->name('events.forms.referral.update');

    Route::post('/events/{event}/forms/{form}/referral-codes', [FormReferralController::class, 'storeCode'])
        ->middleware('throttle:form-referral-write')
        ->name('events.forms.referral-codes.store');

    Route::patch('/events/{event}/forms/{form}/referral-codes/{referralCode}', [FormReferralController::class, 'updateCode'])
        ->middleware('throttle:form-referral-write')
        ->name('events.forms.referral-codes.update');

    Route::delete('/events/{event}/forms/{form}/referral-codes/{referralCode}', [FormReferralController::class, 'destroyCode'])
        ->middleware('throttle:form-referral-write')
        ->name('events.forms.referral-codes.destroy');

    Route::patch('/events/{event}/forms/{form}/submissions/{formAnswer}/review', FormAnswerReviewController::class)
        ->name('events.forms.submissions.review');
});

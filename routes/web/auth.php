<?php

use App\Http\Controllers\Auth\CancelPendingRegistrationController;
use App\Http\Controllers\Auth\EmailAvailabilityController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\VerifyOtpController;
use App\Http\Middleware\PreventAuthPageCaching;
use Illuminate\Support\Facades\Route;

Route::redirect('/auth', '/auth/login')
    ->middleware([PreventAuthPageCaching::class, 'guest']);
Route::middleware([PreventAuthPageCaching::class, 'guest'])->group(function () {
    Route::resource('/auth/login', LoginController::class)
    ->middleware('guest')
    ->middlewareFor('store', 'throttle:auth-login')
    ->names([
        'index' => 'auth.login',
        'store' => 'auth.login.store',
    ])->only(['index', 'store']);

    Route::resource('/auth/register', RegisterController::class)
    ->middleware('guest')
    ->middlewareFor('store', ['throttle:auth-register', 'throttle:auth-otp-address'])
    ->names([
        'index' => 'auth.register',
        'store' => 'auth.register.store',
    ])->only(['index', 'store']);

    Route::get('/auth/verify-otp', [VerifyOtpController::class, 'index'])->name('auth.otp.show');

    Route::post('/auth/verify-otp', [VerifyOtpController::class, 'verify'])
        ->middleware('throttle:auth-otp-verify')
        ->name('auth.otp.verify');

    Route::post('/auth/verify-otp/resend', [VerifyOtpController::class, 'resend'])
        ->middleware('throttle:auth-otp-resend')
        ->name('auth.otp.resend');

    Route::post('/auth/verify-otp/cancel', CancelPendingRegistrationController::class)->name('auth.otp.cancel');

    // Dipanggil debounce dari form register untuk menandai email yang sudah terpakai.
    Route::post('/auth/email-availability', EmailAvailabilityController::class)
        ->middleware('throttle:auth-email-check')
        ->name('auth.email.availability');

    Route::get('/auth/google', [OAuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/github', [OAuthController::class, 'redirectToGithub'])->name('auth.github');

    Route::get('/auth/forgot-password', [ForgotPasswordController::class, 'index'])->name('auth.password.request');
    Route::post('/auth/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:auth-password-email')
        ->name('auth.password.email');
    Route::post('/auth/password-reset-link', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:auth-password-link')
        ->name('auth.password.reset-link');

    Route::get('/auth/reset-password/{token}', [ResetPasswordController::class, 'index'])->name('password.reset');
    Route::post('/auth/reset-password/{token}', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:auth-password-reset')
        ->name('auth.password.update');
});
Route::post('/auth/logout', LogoutController::class)
    ->middleware('auth')
    ->name('auth.logout');

// Verifikasi email akun: halaman untuk user login (bukan guest), bukan bagian alur registrasi.
Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::post('/email/verify/send', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:verify-email-send')
        ->name('verification.send');

    Route::post('/email/verify/confirm', [EmailVerificationController::class, 'confirm'])
        ->middleware('throttle:verify-email-confirm')
        ->name('verification.confirm');
});

// Tantangan faktor kedua: satu-satunya tujuan gerbang `two-factor`, jadi route-nya
// sengaja berada di luar grup `guest` tetapi tetap di dalam `auth`.
Route::middleware('auth')->group(function (): void {
    Route::get('/auth/two-factor', [TwoFactorChallengeController::class, 'show'])
        ->name('two-factor.challenge');

    Route::post('/auth/two-factor', [TwoFactorChallengeController::class, 'verify'])
        ->middleware('throttle:two-factor-verify')
        ->name('two-factor.verify');

    Route::post('/auth/two-factor/recovery', [TwoFactorChallengeController::class, 'sendRecovery'])
        ->middleware('throttle:two-factor-recovery')
        ->name('two-factor.recovery.send');

    Route::post('/auth/two-factor/recovery/confirm', [TwoFactorChallengeController::class, 'confirmRecovery'])
        ->name('two-factor.recovery.confirm');

    Route::post('/auth/two-factor/recovery/pin', [TwoFactorChallengeController::class, 'setRecoveryPin'])
        ->name('two-factor.recovery.pin');
});

// Callback dipakai bersama alur login (guest) dan bind (user login), jadi tidak di dalam grup `guest`.
Route::get('/auth/google/callback', [OAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::get('/auth/github/callback', [OAuthController::class, 'handleGithubCallback'])->name('auth.github.callback');

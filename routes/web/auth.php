<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Middleware\PreventAuthPageCaching;
use Illuminate\Support\Facades\Route;

// use App\Http\Controllers\Auth\PageController as AuthPageController;

Route::redirect('/auth', '/auth/login');

// Routes for login and register
Route::middleware([PreventAuthPageCaching::class, 'guest'])->group(function () {
    // Route::get('/auth', AuthPageController::class)->middleware('guest')->name('auth.login');

    /**
     * Routes for login
     * GET: index [auth/login]
     * POST: store [auth/login]
     */
    Route::resource('/auth/login', LoginController::class)
    ->middleware('guest')
    ->middlewareFor('store', 'throttle:auth-login')
    ->names([
        'index' => 'auth.login',
        'store' => 'auth.login.store',
    ])->only(['index', 'store']);

    /**
     * Routes for registration
     * GET: index [auth/login]
     * POST: store [auth/login]
     */
    Route::resource('/auth/register', RegisterController::class)
    ->middleware('guest')
    ->middlewareFor('store', 'throttle:auth-register')
    ->names([
        'index' => 'auth.register',
        'store' => 'auth.register.store',
    ])->only(['index', 'store']);

    Route::get('/auth/google', [OAuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [OAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
    Route::get('/auth/github', [OAuthController::class, 'redirectToGithub'])->name('auth.github');
    Route::get('/auth/github/callback', [OAuthController::class, 'handleGithubCallback'])->name('auth.github.callback');

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

// Routes for logout
Route::post('/auth/logout', LogoutController::class)
    ->middleware('auth')
    ->name('auth.logout');

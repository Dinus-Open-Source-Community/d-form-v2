<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\PageController as AuthPageController;

// Routes for login and register
Route::middleware('guest')->group(function () {
    // Route::get('/auth', AuthPageController::class)->middleware('guest')->name('auth.login');

    Route::get('/auth', function () {
        return inertia('Auth');
    })->middleware('guest')->name('auth.login');

    Route::get('/auth/google', [OAuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [OAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
    Route::get('/auth/github', [OAuthController::class, 'redirectToGithub'])->name('auth.github');
    Route::get('/auth/github/callback', [OAuthController::class, 'handleGithubCallback'])->name('auth.github.callback');
});

// Routes for logout
Route::post('/auth/logout', LogoutController::class)
    ->middleware('auth')
    ->name('auth.logout');

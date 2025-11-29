<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PageController as AuthPageController;
use Illuminate\Support\Facades\Route;

// Routes for Landing page
Route::get('/', function () {
    return view('welcome');
});

// End of Routes for Landing page

// Routes for Auth
Route::get('/auth', AuthPageController::class)->middleware('guest')->name('auth.login');

Route::post('/auth/logout', LogoutController::class)->name('auth.logout');
// End of Routes for Auth

// Routes for redirect to /auth
Route::middleware('guest')->get('/admin', fn () => to_route('auth.login'));
// End of Routes for redirect to /auth

// Routes for Dashboard
Route::name('dashboard.')->prefix('/dashboard')->middleware('auth')->group(function () {
    Route::get('/', function () {
        return view('dashboard.home');
    })->name('home');
});
// End of Routes for Dashboard

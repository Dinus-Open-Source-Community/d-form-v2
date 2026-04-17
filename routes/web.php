<?php

use Symfony\Component\Finder\Finder;

$path = __DIR__ . '/web';

$finder = new Finder();

$finder->files()->in("{$path}")->name('/^.*\.php$/');

// $files = File::glob("{$path}/{*.php,**/*.php,**/**/*.php}");

// End of Routes for Landing page

// Routes for Auth

// Route::post('/auth/logout', LogoutController::class)->name('auth.logout');

// // OAuth Routes
// Route::middleware('guest')->group(function () {
//     Route::get('/auth/google', [OAuthController::class, 'redirectToGoogle'])->name('auth.google');
//     Route::get('/auth/google/callback', [OAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
//     Route::get('/auth/github', [OAuthController::class, 'redirectToGithub'])->name('auth.github');
//     Route::get('/auth/github/callback', [OAuthController::class, 'handleGithubCallback'])->name('auth.github.callback');
// });
// // End of Routes for Auth

// // Routes for redirect to /auth or /dashboard
// Route::middleware('auth')->get('/admin', fn () => to_route('dashboard.home'));
// // End of Routes for redirect to /auth

// // Routes for Dashboard
// Route::name('dashboard.')->prefix('/dashboard')->middleware('auth')->group(function () {
//     Route::get('/', function () {
//         return view('pages.dashboard.home');
//     })->name('home');

//     Route::resource('/events', EventController::class)->only(['index', 'create', 'show', 'edit']);
//     Route::resource('/events/{event}/forms', EventFormController::class)->only(['index', 'create', 'show', 'edit'])
//         ->names('events.forms');
// });
// End of Routes for Dashboard
foreach ($finder as $file) {
    require_once $file->getRealPath();
}

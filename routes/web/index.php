<?php

use App\Http\Controllers\DocsPageController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\FeaturePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    
    return inertia('Index');
})->name('home');

Route::get('/features', FeaturePageController::class)->name('features');

Route::resource('/events', EventsController::class)->only(['index', 'show']);

Route::get('/docs', DocsPageController::class)->name('docs');


Route::get('/info', function () {
    die(phpinfo());
})->name('info');
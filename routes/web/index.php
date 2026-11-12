<?php

use App\Http\Controllers\DocsPageController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\FeaturePageController;
use App\Http\Controllers\Seo\RobotsTxtController;
use App\Http\Controllers\Seo\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', RobotsTxtController::class)->name('seo.robots');
Route::get('/sitemap.xml', SitemapController::class)->name('seo.sitemap');

Route::get('/', function () {

    return inertia('Index');
})->name('home');

Route::get('/features', FeaturePageController::class)->name('features');

Route::resource('/events', EventsController::class)
    ->only(['index', 'show'])
    ->middlewareFor('index', 'throttle:event-search')
    ->where(['event' => '^(?!joined(/|$)).+']);

Route::get('/docs', DocsPageController::class)->name('docs');

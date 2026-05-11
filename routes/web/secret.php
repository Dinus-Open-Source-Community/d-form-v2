<?php

use Illuminate\Support\Facades\Route;

/*
| Secret / easter-egg routes (not linked from the app). Same experience, two URLs.
*/
Route::get('/party', static fn () => inertia('SecretParty'))->name('secret.party');
Route::get('/balloons', static fn () => inertia('SecretParty'))->name('secret.balloons');

<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->get('/admin', fn () => to_route('dashboard.home'));

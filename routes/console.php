<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cache dashboard harus diisi di luar request path; scheduler/cron (`php artisan schedule:run`
// tiap menit) wajib aktif, atau jalankan `php artisan dashboard:warm` manual setelah deploy.
Schedule::command('dashboard:warm')->everyFiveMinutes();

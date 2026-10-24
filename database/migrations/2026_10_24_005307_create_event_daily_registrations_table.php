<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agregat harian registrasi untuk chart dashboard: satu baris per tanggal,
 * sehingga chart tidak perlu menjalankan join 1 juta baris tiap cache dingin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_daily_registrations', function (Blueprint $table): void {
            $table->date('registration_date')->primary();
            $table->unsignedBigInteger('count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_daily_registrations');
    }
};

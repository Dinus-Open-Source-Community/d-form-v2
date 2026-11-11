<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL DDL tidak transaksional: setelah ALTER sukses, migrasi bisa crash sebelum tercatat.
 * Guard ini membuat re-run menjadi no-op alih-alih gagal "Duplicate column".
 *
 * Biaya rollback: `down()` melakukan DROP COLUMN. Pada MariaDB 10.11 (dan MySQL tanpa
 * INSTANT DROP) ini memicu rebuild tabel penuh dengan ALGORITHM=COPY, jadi blocking dan mahal
 * pada `recruitment_applications` berskala 1 juta baris (disk sementara ~seukuran tabel +
 * index). Rollback hanya untuk keadaan darurat: jalankan di maintenance window, dengan disk
 * lega, dan backup sebelum eksekusi. Jangan jadikan bagian dari rollback rutin.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('recruitment_applications', 'tracking_token_generation')) {
            return;
        }

        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->unsignedBigInteger('tracking_token_generation')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('recruitment_applications', 'tracking_token_generation')) {
            return;
        }

        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->dropColumn('tracking_token_generation');
        });
    }
};

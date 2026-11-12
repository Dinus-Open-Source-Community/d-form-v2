<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index laporan log kehadiran event: `WHERE event_id = ? ORDER BY scanned_at DESC`.
 * Tanpa index ini MySQL memakai unique `(event_id, form_answer_id)` untuk filter
 * lalu mengurutkan hasilnya (filesort). Kolom ascending tetap melayani DESC lewat
 * backward index scan; perilaku itu diverifikasi manual di MySQL 9 dev, sedangkan
 * regresi EXPLAIN otomatis berjalan hanya saat koneksi MySQL tersedia (selain itu
 * test di-skip).
 *
 * Idempoten: index bisa sudah dibuat manual saat benchmark, dan MySQL tidak
 * membungkus DDL dalam transaksi sehingga kegagalan di tengah bisa meninggalkan
 * index tanpa baris migrasi.
 */
return new class () extends Migration {
    private const TABLE = 'event_attendances';

    private const INDEX = 'event_attendances_event_id_scanned_at_index';

    public function up(): void
    {
        if (Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['event_id', 'scanned_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};

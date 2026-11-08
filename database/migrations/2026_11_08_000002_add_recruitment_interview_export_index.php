<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk keyset pagination export CSV interview:
 * `WHERE recruitment_period_id = ? AND registration_number > ? ORDER BY registration_number`.
 * Tanpa index ini MySQL memakai unique index `registration_number` dan
 * mengestimasi ratusan ribu baris untuk halaman akhir.
 *
 * Idempoten: index mungkin sudah dibuat manual saat benchmark, dan MySQL tidak
 * membungkus DDL dalam transaksi sehingga kegagalan di tengah bisa meninggalkan
 * index tanpa baris migrasi.
 */
return new class () extends Migration {
    private const TABLE = 'recruitment_applications';

    private const INDEX = 'rec_apps_period_regno_idx';

    public function up(): void
    {
        if (Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['recruitment_period_id', 'registration_number'], self::INDEX);
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

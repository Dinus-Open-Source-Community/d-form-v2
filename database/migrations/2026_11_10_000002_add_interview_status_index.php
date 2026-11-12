<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Covering index untuk agregat interview pada laporan recruitment:
 * `WHERE recruitment_application_id IN (...period...) GROUP BY status`.
 *
 * Tanpa index ini MySQL menjalankan nested loop 20.000 kali memakai
 * `rec_interviews_app_kind_uniq` lalu membaca baris tabel untuk kolom `status`
 * (10,2 detik pada 1 juta baris, audit EP-03). Dengan `(recruitment_application_id,
 * status)` lookup menjadi index-only/covering.
 *
 * `rec_interviews_app_kind_uniq` TETAP dipakai: index tersebut adalah constraint
 * unique (satu interview primary + satu secondary per application), bukan index
 * pelaporan, sehingga tidak boleh dihapus tanpa approval terpisah.
 *
 * Idempoten: MySQL tidak membungkus DDL dalam transaksi, kegagalan di tengah
 * meninggalkan index tanpa baris migrasi.
 */
return new class () extends Migration {
    private const TABLE = 'recruitment_interviews';

    private const INDEX = 'rec_interviews_app_status_idx';

    public function up(): void
    {
        if (Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['recruitment_application_id', 'status'], self::INDEX);
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

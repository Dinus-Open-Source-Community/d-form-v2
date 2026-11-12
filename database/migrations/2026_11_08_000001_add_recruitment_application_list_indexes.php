<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index komposit untuk daftar pelamar pada tab "Peserta" halaman periode
 * recruitment. Query `WHERE recruitment_period_id = ? ORDER BY submitted_at
 * DESC, id DESC` sebelumnya memicu filesort + full scan ±20.000 baris/periode
 * (5,2-6,2 detik pada 1 juta baris; audit 5,15 detik). Ketiga index menghapus
 * filesort tersebut.
 *
 * FULLTEXT sengaja tidak dipakai: index FULLTEXT bersifat table-wide (pencarian
 * awalan NIM `A11` menjadi ±19-22 detik per ketikan vs 8 ms halaman + 4,35 detik
 * COUNT dengan LIKE) dan `ALTER TABLE ADD FULLTEXT` tidak online di MariaDB
 * (ERROR 1846, ±7 menit write lock pada 1 juta baris) sementara entrypoint
 * container menjalankan `migrate --force` saat boot. Lihat laporan review
 * BE-P2-001 (F1/F2).
 *
 * Idempoten: MySQL tidak membungkus DDL dalam transaksi, sehingga kegagalan di
 * tengah meninggalkan index yang sudah dibuat tanpa baris di `migrations`.
 * Menjalankan ulang harus melewati index yang ada, bukan gagal dengan error 1061.
 */
return new class () extends Migration {
    private const TABLE = 'recruitment_applications';

    public function up(): void
    {
        $this->addIndexIfMissing('rec_apps_period_submitted_idx', ['recruitment_period_id', 'submitted_at']);
        $this->addIndexIfMissing('rec_apps_period_stage_submitted_idx', ['recruitment_period_id', 'stage', 'submitted_at']);
        $this->addIndexIfMissing('rec_apps_period_semester_submitted_idx', ['recruitment_period_id', 'semester', 'submitted_at']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('rec_apps_period_submitted_idx');
        $this->dropIndexIfExists('rec_apps_period_stage_submitted_idx');
        $this->dropIndexIfExists('rec_apps_period_semester_submitted_idx');
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $indexName, array $columns): void
    {
        if (Schema::hasIndex(self::TABLE, $indexName)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $indexName): void
    {
        if (! Schema::hasIndex(self::TABLE, $indexName)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }
};

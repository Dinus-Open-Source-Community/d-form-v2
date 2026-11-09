<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk hitungan eligibility QR interview OpRec (BE-P2-003).
 *
 * Kenapa: `qr_eligible_count`/`qr_sent_count` di halaman periode dan pool
 * penerima bulk service memakai filter dasar applicant + subquery EXISTS pada
 * `email_logs` (notification_type + status). Audit pada 1 juta baris mengukur
 * 1,98-2,20 detik per COUNT sebelum index. `EXPLAIN ANALYZE` setelah kedua index
 * menunjukkan lookup covering pada `email_logs_rec_app_type_status_idx` dan
 * `rec_apps_period_stage_cancelled_idx`: ±0,85-0,95 detik (128 MB buffer pool)
 * dan ±27-47 ms dengan pool 1 GB hangat. Ukuran index kecil karena kolomnya
 * pendek (UUID + varchar bertipe enum), jadi biaya tulis tambahan minimal.
 *
 * Idempoten: MySQL tidak membungkus DDL dalam transaksi, sehingga kegagalan di
 * tengah meninggalkan index yang sudah dibuat tanpa baris di `migrations`.
 * Entrypoint container juga menjalankan `migrate --force` saat boot, jadi
 * menjalankan ulang harus melewati index yang sudah ada (dan index dengan nama
 * ini sudah dibuat manual saat benchmark) alih-alih gagal dengan error 1061.
 *
 * Rollback aman: `down()` hanya menghapus index tambahan; tidak ada perubahan
 * atau kehilangan data.
 */
return new class () extends Migration {
    public function up(): void
    {
        $this->addIndexIfMissing(
            'email_logs',
            'email_logs_rec_app_type_status_idx',
            ['recruitment_application_id', 'notification_type', 'status'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_stage_cancelled_idx',
            ['recruitment_period_id', 'stage', 'cancelled_at'],
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('email_logs', 'email_logs_rec_app_type_status_idx');
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_stage_cancelled_idx');
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        // Cek berdasarkan kolom (bukan nama) supaya index identik yang sudah ada
        // dengan nama lain tidak terduplikasi saat migrate dijalankan ulang.
        if (Schema::hasIndex($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropIndex($indexName);
        });
    }
};

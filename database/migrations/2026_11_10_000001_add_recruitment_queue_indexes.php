<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index pendukung EP-03 untuk halaman periode recruitment (20.000 applicant
 * per periode pada dataset 1 juta baris).
 *
 * Kenapa tiap index ada (diukur di dev MySQL 9.0.1, buffer pool 128 MB):
 * - `rec_apps_queue_counts_idx`: agregat badge antrean (`queueCounts`) memakai
 *   WHERE period + SUM(CASE stage/result/revision_required/cancelled_at).
 *   Sebelum: scan 36.690 baris via `rec_apps_period_result_idx`, 5.500-5.800 ms.
 *   Sesudah: `Extra=Using index`, ~15 ms.
 * - `rec_apps_period_secondary_division_idx`: total paginasi filter divisi
 *   dihitung sebagai UNION dua covering count (primary + secondary). Index
 *   primary sudah ada (`rec_apps_period_div_idx`); versi secondary belum.
 *   Sebelum: COUNT atas OR 5.800 ms. Sesudah (UNION): ~19 ms.
 * - `rec_apps_period_full_name_idx`: pencarian menjadi prefix (`term%`) supaya
 *   bisa memakai range index. NIM/registration number sudah punya composite
 *   (period + kolom). Sebelum: LIKE '%term%' 4.500-5.800 ms. Sesudah: ~1 ms
 *   untuk COUNT dan ~1,3 ms untuk halaman id.
 * - `rec_screenings_decision_app_idx`: `groupLinkEligibleCount` memakai EXISTS
 *   screening decision=pass untuk seluruh applicant periode. Urutan decision
 *   lebih dulu (bukan app_id) karena `decision = 'pass'` konstan pada predikat
 *   ini: probe menjadi satu point-lookup index-only. Terukur 9.000+ ms menjadi
 *   ~80 ms; urutan app_id-first masih ~1.500 ms karena optimizer memindai prefix.
 * - `rec_correction_app_status_idx`: `corrections_pending` di `queueCounts`
 *   mencari koreksi pending milik applicant periode. Sebelum ~490 ms, dengan
 *   index covering ~80-300 ms.
 *
 * Non-destruktif dan aman di 1 juta baris: hanya ADD INDEX (InnoDB online DDL).
 * Idempoten: MySQL tidak membungkus DDL dalam transaksi dan entrypoint container
 * menjalankan `migrate --force` saat boot, jadi index yang sudah ada harus
 * dilewati (dicek berdasarkan daftar kolom, bukan hanya nama).
 */
return new class () extends Migration {
    public function up(): void
    {
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_queue_counts_idx',
            ['recruitment_period_id', 'cancelled_at', 'stage', 'result', 'revision_required'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_secondary_division_idx',
            ['recruitment_period_id', 'secondary_division_id'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_full_name_idx',
            ['recruitment_period_id', 'full_name'],
        );
        $this->addIndexIfMissing(
            'recruitment_screenings',
            'rec_screenings_decision_app_idx',
            ['decision', 'recruitment_application_id'],
        );
        $this->addIndexIfMissing(
            'recruitment_correction_requests',
            'rec_correction_app_status_idx',
            ['recruitment_application_id', 'status'],
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_queue_counts_idx');
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_secondary_division_idx');
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_full_name_idx');
        $this->dropIndexIfExists('recruitment_screenings', 'rec_screenings_decision_app_idx');
        $this->dropIndexIfExists('recruitment_correction_requests', 'rec_correction_app_status_idx');
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        // Bandingkan kolom, bukan nama: index identik dengan nama lain (mis. hasil
        // ALTER manual saat benchmark) tidak boleh diduplikasi.
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

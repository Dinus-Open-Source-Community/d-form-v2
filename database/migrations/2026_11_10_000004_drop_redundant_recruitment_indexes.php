<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus index redundan recruitment (HYG-004 / BE-P2-016 + H1 dari 2026_11_10_000005).
 * DESTRUKTIF: butuh approval operator.
 *
 * Yang dihapus (left prefix dari index pengganti, jadi lookup lama tetap terlayani):
 * - `rec_apps_period_stage_idx (recruitment_period_id, stage)` → prefix
 *   `rec_apps_period_stage_cancelled_idx (recruitment_period_id, stage, cancelled_at)`.
 * - `email_logs_rec_app_idx (recruitment_application_id)` → prefix
 *   `email_logs_rec_app_type_status_idx (recruitment_application_id, notification_type, status)`.
 *   FK `email_logs_rec_app_fk` tetap punya index pendukung di kolom terkiri.
 * - `rec_apps_period_regno_idx (recruitment_period_id, registration_number)` → prefix
 *   `rec_apps_period_regno_cover_idx (recruitment_period_id, registration_number, submitted_at)`
 *   dari `2026_11_10_000005`; versi covering juga menghapus table-read untuk `submitted_at`.
 * - `rec_apps_period_full_name_idx (recruitment_period_id, full_name)` → prefix
 *   `rec_apps_period_full_name_submitted_idx (recruitment_period_id, full_name, submitted_at)`.
 *
 * Yang SENGAJA DIPERTAHANKAN: `email_logs_notification_type_index (notification_type)`.
 * Cardinality-nya rendah (±1 pada 1 juta baris) sehingga tampak tidak berguna, tetapi index ini
 * adalah plan driver `RecruitmentPeriodController::qrStatusSummary()` (window function di atas
 * join `email_logs`→`recruitment_applications`): 0,8 ms dengan index, 4,2 s dengan `IGNORE INDEX`.
 * Menghapusnya akan mengembalikan latensi ringkasan QR tab peserta.
 *
 * Ukuran index di dev (1 juta baris/tabel): stage 151,00 MB + rec_app 73,77 MB +
 * regno 111,98 MB + full_name 104,98 MB = ±441,7 MB (pasangan H1 sendiri ±217 MB),
 * plus biaya tulis per INSERT/UPDATE. `email_logs_notification_type_index` (108,97 MB) tetap.
 *
 * Gate approval: entrypoint dev menjalankan `migrate --force` setiap boot, jadi drop tidak
 * boleh jalan tanpa persetujuan user. `up()` sengaja no-op kecuali
 * `APPLY_REDUNDANT_INDEX_DROP=true` di environment.
 *
 * Gate produksi (hard guard): `up()` juga menolak berjalan saat `app()->isProduction()`,
 * sekalipun flag diset. Entrypoint prod menjalankan `migrate --force` sebelum `config:cache`,
 * sehingga nilai env yang salah pada container baru tidak boleh bisa men-drop index saat boot.
 * Drop di produksi adalah one-off manual SETELAH approval, bukan bagian dari boot migration:
 *   1. `SHOW INDEX FROM ...` + backup/monitoring plan disetujui
 *   2. `DROP INDEX rec_apps_period_stage_idx ON recruitment_applications;`
 *      `DROP INDEX rec_apps_period_regno_idx ON recruitment_applications;`
 *      `DROP INDEX rec_apps_period_full_name_idx ON recruitment_applications;`
 *      `DROP INDEX email_logs_rec_app_idx ON email_logs;`
 *   3. catat di changelog + runbook; `php artisan migrate --force` berikutnya hanya mencatat
 *      migration ini sebagai no-op.
 *
 * Prosedur dev/staging saat sudah disetujui:
 *   1. set `APPLY_REDUNDANT_INDEX_DROP=true` (bukan `APP_ENV=production`)
 *   2. `php artisan migrate:rollback --path=database/migrations/2026_11_10_000004_drop_redundant_recruitment_indexes.php`
 *   3. `php artisan migrate --path=database/migrations/2026_11_10_000004_drop_redundant_recruitment_indexes.php`
 * Rollback penuh selalu aman: `down()` membuat ulang empat index dengan nama dan kolom yang sama
 * persis seperti definisi asalnya, tanpa menyentuh data (berlaku juga di produksi).
 */
return new class () extends Migration {
    private const APPROVAL_FLAG = 'APPLY_REDUNDANT_INDEX_DROP';

    public function up(): void
    {
        if (app()->isProduction()) {
            echo 'Skipped: production never drops indexes during boot; run the approved DROP INDEX manually.'.PHP_EOL;

            return;
        }

        if (! $this->approved()) {
            echo 'Skipped: redundant index drop needs approval ('.self::APPROVAL_FLAG.'=true).'.PHP_EOL;

            return;
        }

        // Index pengganti wajib ada lebih dulu; tanpa itu drop meninggalkan query lambat
        // (dan di MySQL bisa ditolak karena FK kehilangan index pendukung).
        // `000004` berjalan SEBELUM `000005` (urutan nama file), sehingga pada fresh install
        // pasangan H1 belum punya index covering saat migration ini jalan. Pastikan prasyaratnya
        // ada dulu dengan definisi yang sama; `000005` kemudian menjadi no-op (guard by columns).
        // Di dev/prod yang sudah menjalankan `000005`, dua panggilan ini no-op.
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_regno_cover_idx',
            ['recruitment_period_id', 'registration_number', 'submitted_at'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_full_name_submitted_idx',
            ['recruitment_period_id', 'full_name', 'submitted_at'],
        );

        $this->dropIfCoveredBy(
            'recruitment_applications',
            'rec_apps_period_stage_idx',
            'rec_apps_period_stage_cancelled_idx',
        );
        $this->dropIfCoveredBy(
            'email_logs',
            'email_logs_rec_app_idx',
            'email_logs_rec_app_type_status_idx',
        );
        $this->dropIfCoveredBy(
            'recruitment_applications',
            'rec_apps_period_regno_idx',
            'rec_apps_period_regno_cover_idx',
        );
        $this->dropIfCoveredBy(
            'recruitment_applications',
            'rec_apps_period_full_name_idx',
            'rec_apps_period_full_name_submitted_idx',
        );
    }

    public function down(): void
    {
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_stage_idx',
            ['recruitment_period_id', 'stage'],
        );
        $this->addIndexIfMissing('email_logs', 'email_logs_rec_app_idx', ['recruitment_application_id']);
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_regno_idx',
            ['recruitment_period_id', 'registration_number'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_full_name_idx',
            ['recruitment_period_id', 'full_name'],
        );
    }

    /**
     * Drop index lama hanya bila index penggantinya ada.
     */
    private function dropIfCoveredBy(string $table, string $indexName, string $coveringIndex): void
    {
        if (! Schema::hasIndex($table, $indexName) || ! Schema::hasIndex($table, $coveringIndex)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropIndex($indexName);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (Schema::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
            $blueprint->index($columns, $indexName);
        });
    }

    /**
     * Approval eksplisit; `env()` dibaca runtime karena file ini tidak memakai config ter-cache.
     *
     * Produksi dikecualikan keras: entrypoint prod menjalankan `migrate --force` sebelum
     * `config:cache`, jadi flag env saja tidak boleh menjadi izin drop saat boot.
     */
    private function approved(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        $value = env(self::APPROVAL_FLAG);

        if (is_bool($value)) {
            return $value;
        }

        return is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
};
